"""Synthetic, network-free tests of the paid deployment stop boundary."""
import contextlib
from datetime import datetime, timedelta, timezone
import importlib.util
import io
from pathlib import Path
import unittest

spec = importlib.util.spec_from_file_location("stop_defense", Path(__file__).resolve().parents[1] / "deploy/stop-defense.py")
controller = importlib.util.module_from_spec(spec)
spec.loader.exec_module(controller)


class StopTests(unittest.TestCase):
    def setUp(self):
        self.env = dict(STOP_RENDER_API_KEY="rnd_" + "x" * 24, STOP_WORKSPACE_ID="tea-" + "a" * 20,
                        STOP_WEB_ID="srv-" + "b" * 20, STOP_CONTROLLER_ID="crn-" + "c" * 20,
                        STOP_WEB_NAME="avela-defense-test", STOP_DEADLINE_UTC="2026-10-31T20:00:00Z")
        self.config = controller.configuration(self.env)
        self.now = self.config["deadline"]
        self.calls = []
        self.web_suspended = False
        self.wrong_owner = False
        self.post_fails = False
        self.confirm = True
        self.name_override = None

    def request(self, key, method, identity, action=""):
        self.calls.append((method, identity, action))
        if method == "POST":
            if self.post_fails:
                raise RuntimeError("Synthetic provider failure")
            if identity == self.config["STOP_WEB_ID"] and self.confirm:
                self.web_suspended = True
            return
        web = identity == self.config["STOP_WEB_ID"]
        return dict(id=identity, ownerId="wrong" if self.wrong_owner else self.config["STOP_WORKSPACE_ID"],
                    name=(self.name_override or self.config["web_names"][0]) if web else "avela-defense-auto-stop",
                    type="web_service" if web else "cron_job",
                    suspended="suspended" if web and self.web_suspended else "not_suspended")

    def run_controller(self, **kwargs):
        with contextlib.redirect_stdout(io.StringIO()):
            controller.run(self.config, kwargs.pop("now", self.now), self.request, lambda _: None, **kwargs)

    def posts(self):
        return [call[1] for call in self.calls if call[0] == "POST"]

    def test_before_deadline_no_mutation(self):
        self.run_controller(now=self.now - timedelta(seconds=1))
        self.assertEqual(self.posts(), [])

    def test_due_suspends_web_then_controller(self):
        self.run_controller()
        self.assertEqual(self.posts(), [self.config["STOP_WEB_ID"], self.config["STOP_CONTROLLER_ID"]])

    def test_owner_mismatch_never_mutates(self):
        self.wrong_owner = True
        with self.assertRaises(RuntimeError): self.run_controller()
        self.assertEqual(self.posts(), [])

    def test_provider_failure_preserves_retry_controller(self):
        self.post_fails = True
        with self.assertRaises(RuntimeError): self.run_controller()
        self.assertNotIn(self.config["STOP_CONTROLLER_ID"], self.posts())

    def test_unconfirmed_suspension_preserves_retry_controller(self):
        self.confirm = False
        with self.assertRaises(RuntimeError): self.run_controller()
        self.assertNotIn(self.config["STOP_CONTROLLER_ID"], self.posts())

    def test_already_suspended_web_stops_controller(self):
        self.web_suspended = True
        self.run_controller()
        self.assertEqual(self.posts(), [self.config["STOP_CONTROLLER_ID"]])

    def test_audit_never_mutates_even_after_deadline(self):
        self.run_controller(audit_only=True)
        self.assertEqual(self.posts(), [])

    def test_explicit_rename_keeps_same_service_protected(self):
        self.config = controller.configuration(self.env | {"STOP_WEB_NAME": "avela-defense-test,avela"})
        self.name_override = "avela"
        self.run_controller()
        self.assertEqual(self.posts(), [self.config["STOP_WEB_ID"], self.config["STOP_CONTROLLER_ID"]])

    def test_unapproved_name_never_mutates(self):
        self.name_override = "avela-unrelated"
        with self.assertRaises(RuntimeError): self.run_controller()
        self.assertEqual(self.posts(), [])

    def test_reject_ambiguous_or_injected_identity(self):
        for key, value in [("STOP_WEB_ID", self.env["STOP_CONTROLLER_ID"]),
                           ("STOP_CONTROLLER_ID", "../other"), ("STOP_RENDER_API_KEY", "x\nAuthorization: bad"),
                           ("STOP_DEADLINE_UTC", "tomorrow"), ("STOP_WEB_NAME", "other-application"),
                           ("STOP_WEB_NAME", "avela,avela"), ("STOP_WEB_NAME", "avela,avela-care,avela-shop")]:
            with self.subTest(key=key), self.assertRaises(ValueError):
                controller.configuration(self.env | {key: value})


if __name__ == "__main__":
    unittest.main()
