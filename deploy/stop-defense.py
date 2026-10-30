"""Dedicated cloud cron controller. Never install its API key in the web service."""
from datetime import datetime, timezone
import json
import os
import re
import sys
import time
from urllib.error import HTTPError
from urllib.request import Request, build_opener, HTTPRedirectHandler


class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        raise RuntimeError("API redirects are forbidden")


def configuration(environ):
    result = {key: environ.get(key, "") for key in (
        "STOP_RENDER_API_KEY", "STOP_WORKSPACE_ID", "STOP_WEB_ID",
        "STOP_WEB_NAME", "STOP_CONTROLLER_ID", "STOP_DEADLINE_UTC")}
    for key in ("STOP_WEB_ID", "STOP_CONTROLLER_ID"):
        if not re.fullmatch(r"(?:srv|crn)-[a-z0-9]{10,40}", result[key]):
            raise ValueError("Invalid service identity")
    if result["STOP_WEB_ID"] == result["STOP_CONTROLLER_ID"]:
        raise ValueError("Controller must be separate")
    if not re.fullmatch(r"tea-[a-z0-9]{10,40}", result["STOP_WORKSPACE_ID"]):
        raise ValueError("Invalid workspace identity")
    names = result["STOP_WEB_NAME"].split(",")
    if (not 1 <= len(names) <= 2 or len(set(names)) != len(names)
            or any(len(name) > 80 or not re.fullmatch(r"avela(?:-[a-z0-9]+)*", name) for name in names)):
        raise ValueError("Invalid defense service name")
    result["web_names"] = names
    if not re.fullmatch(r"rnd_[A-Za-z0-9_-]{16,200}", result["STOP_RENDER_API_KEY"]):
        raise ValueError("Missing API credential")
    result["deadline"] = datetime.strptime(result["STOP_DEADLINE_UTC"], "%Y-%m-%dT%H:%M:%SZ").replace(tzinfo=timezone.utc)
    return result


def api(credential, method, service_id, action=""):
    # Fixed HTTPS origin, bounded body/time, no cookies, redirects or response logging.
    request = Request("https://api.render.com/v1/services/" + service_id + action,
                      method=method, headers={"Authorization": "Bearer " + credential,
                                               "Accept": "application/json"})
    with build_opener(NoRedirect()).open(request, timeout=20) as response:
        if method == "POST":
            if response.status != 202:
                raise RuntimeError("Suspension was not accepted")
            return None
        if response.status != 200:
            raise RuntimeError("Service verification failed")
        body = response.read(262145)
        if len(body) > 262144:
            raise RuntimeError("Oversized API response")
        return json.loads(body)


def verify_service(service, identity, name, owner, kind):
    names = (name,) if isinstance(name, str) else tuple(name)
    if (service.get("id") != identity or service.get("ownerId") != owner
            or service.get("name") not in names or service.get("type") != kind
            or service.get("suspended") not in ("suspended", "not_suspended")):
        raise RuntimeError("Service identity mismatch")


def run(config, now, request=api, pause=time.sleep, audit_only=False):
    key, web, controller = (config[k] for k in ("STOP_RENDER_API_KEY", "STOP_WEB_ID", "STOP_CONTROLLER_ID"))
    target = request(key, "GET", web)
    verify_service(target, web, config["web_names"], config["STOP_WORKSPACE_ID"], "web_service")
    own = request(key, "GET", controller)
    verify_service(own, controller, "avela-defense-auto-stop", config["STOP_WORKSPACE_ID"], "cron_job")
    if audit_only:
        print("Shutdown controller identities verified; no suspension requested.")
        return
    if now < config["deadline"]:
        print("Defense deadline has not arrived.")
        return
    if target["suspended"] != "suspended":
        request(key, "POST", web, "/suspend")
        for _ in range(10):
            pause(2)
            target = request(key, "GET", web)
            verify_service(target, web, config["web_names"], config["STOP_WORKSPACE_ID"], "web_service")
            if target["suspended"] == "suspended":
                break
        else:
            raise RuntimeError("Web suspension not yet confirmed; retry next cron run")
    print("Defense web suspension confirmed; stopping recurring controller.", flush=True)
    request(key, "POST", controller, "/suspend")


if __name__ == "__main__":
    try:
        if sys.argv[1:] not in ([], ["--audit-only"]):
            raise ValueError("Unsupported mode")
        run(configuration(os.environ), datetime.now(timezone.utc), audit_only="--audit-only" in sys.argv)
    except (Exception,) as error:
        # Do not expose provider response bodies, headers, credentials or URLs.
        print("Defense shutdown check failed (" + type(error).__name__ + "); operator attention required.", file=sys.stderr)
        sys.exit(1)
