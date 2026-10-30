"""Exercise production rejection without private .env, database or provider access."""
import argparse
import base64
import json
from pathlib import Path
import subprocess
import tempfile

parser = argparse.ArgumentParser()
parser.add_argument("--php", default="php")
args = parser.parse_args()
root = Path(__file__).resolve().parents[1]
cases = [
    ({"APP_URL": "http://example.invalid"}, "trusted HTTPS APP_URL"),
    ({"APP_URL": "https://user@example.invalid"}, "trusted HTTPS APP_URL"),
    ({"SESSION_SECURE_COOKIE": False}, "SESSION_SECURE_COOKIE"),
    ({"MAIL_DRIVER": "log"}, "MAIL_DRIVER"),
    ({"DB_TLS_REQUIRED": False}, "DB_TLS_REQUIRED"),
    ({"UPLOAD_SCANNER": "disabled"}, "UPLOAD_SCANNER"),
    ({"RECAPTCHA_SITE_KEY": "6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI"}, "production CAPTCHA keys"),
    ({"MFA_ENCRYPTION_KEY": "invalid"}, "MFA key format"),
    ({"MAYA_SANDBOX_ENABLED": True}, "MAYA_SANDBOX_ENABLED"),
]
with tempfile.TemporaryDirectory(prefix="avela-preflight-") as directory:
    fixture = Path(directory) / "synthetic-file"
    fixture.write_text("Synthetic readable-path fixture; not a deployed key or CA.")
    config = dict(
        APP_ENV="production", APP_DEBUG=False, APP_URL="https://example.invalid",
        DB_HOST="db.example.invalid", DB_DATABASE="synthetic", DB_USERNAME="synthetic_app",
        DB_PASSWORD="FixtureDatabaseSecret9!", DB_TLS_REQUIRED=True, DB_SSL_CA=str(fixture),
        MAIL_DRIVER="smtp", MAIL_HOST="smtp.example.invalid", MAIL_USERNAME="fixture@example.invalid",
        MAIL_PASSWORD="FixtureSMTPSecret9!", MAIL_FROM_ADDRESS="fixture@example.invalid",
        MAIL_ENCRYPTION="tls", MAIL_LOCAL_STAFF_OTP=False, SESSION_SECURE_COOKIE=True,
        RECAPTCHA_SITE_KEY="synthetic-site-key", RECAPTCHA_SECRET_KEY="synthetic-secret-key",
        MFA_ENCRYPTION_KEY=base64.b64encode(bytes(range(32))).decode(),
        PII_ENCRYPTION_ENABLED=True, PII_KEYRING_FILE=str(fixture), UPLOAD_SCANNER="clamd",
        MAYA_SANDBOX_ENABLED=False, MAYA_LOCAL_HOSTED_SANDBOX=False,
        MAYA_LOCAL_SIMULATION=False, MAYA_WEBHOOK_READY=False, MAYA_RECONCILIATION_READY=False,
    )
    command = [args.php, "-r", "$_ENV=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);require 'deploy/check-environment.php';"]
    baseline = subprocess.run(command, cwd=root, input=json.dumps(config), text=True, capture_output=True, timeout=10)
    baseline_text = baseline.stdout + baseline.stderr
    # Windows lacks posix_kill; this does not simulate a Linux container build.
    if baseline.returncode:
        failures = [line[2:] for line in baseline_text.splitlines() if line.startswith("- ")]
        assert failures == ["posix_kill"], baseline_text
    for override, expected in cases:
        result = subprocess.run(command, cwd=root, input=json.dumps(config | override), text=True, capture_output=True, timeout=10)
        output = result.stdout + result.stderr
        assert result.returncode != 0 and expected in output, expected
        assert "FixtureDatabaseSecret9!" not in output and "FixtureSMTPSecret9!" not in output
print(f"PASS: {len(cases) + 1} production preflight configuration cases; no network requests.")
