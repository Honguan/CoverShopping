"""Exercise the Compose MySQL health probe during real initialization and startup."""

import json
import os
from pathlib import Path
import shlex
import subprocess
import tempfile
import time
import uuid


def docker(*args, check=True):
    return subprocess.run(
        ["docker", *args], check=check, capture_output=True, text=True, timeout=30
    )


def wait_for(check, description, container):
    deadline = time.monotonic() + 120
    while time.monotonic() < deadline:
        if check():
            return
        assert docker("inspect", "--format", "{{.State.Running}}", container).stdout.strip() == "true", \
            f"Container stopped while waiting for {description}"
        time.sleep(1)
    raise AssertionError(f"Timed out waiting for {description}")


def main():
    root = Path(__file__).resolve().parents[2]
    env = os.environ | {
        "APP_KEY": "base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=",
        "DB_DATABASE": "healthcheck_test",
        "DB_USERNAME": "healthcheck_user",
        "DB_PASSWORD": "health check-$password",
        "MYSQL_ROOT_PASSWORD": "healthcheck-root",
    }
    config = subprocess.run(
        ["docker", "compose", "--env-file", ".env.example", "config", "--format", "json"],
        cwd=root, env=env, check=True, capture_output=True, text=True, timeout=30,
    )
    # Compose config re-escapes dollar signs for round-tripping; restore container values.
    service = json.loads(config.stdout.replace("$$", "$"))["services"]["db"]
    probe = service["healthcheck"]["test"]
    command = probe[1] if probe[0] == "CMD-SHELL" else shlex.join(probe[1:])
    name = "covershopping-health-" + uuid.uuid4().hex[:12]
    args = ["create", "--name", name, "--network", "none", "--tmpfs", "/var/lib/mysql",
            "--health-cmd", command, "--health-interval", "1s", "--health-timeout", "3s",
            "--health-retries", "1", "-e", "MYSQL_INITDB_SKIP_TZINFO=1"]
    for key, value in service["environment"].items():
        args.extend(["-e", f"{key}={value}"])
    docker(*args, service["image"])
    try:
        with tempfile.TemporaryDirectory(prefix="covershopping-health-") as directory:
            gate = Path(directory) / "hold-init.sh"
            gate.write_text(
                "touch /tmp/initializing\n"
                "while [ ! -f /tmp/release-init ]; do sleep 1; done\n",
                encoding="utf-8", newline="\n",
            )
            docker("cp", str(gate), f"{name}:/docker-entrypoint-initdb.d/hold-init.sh")
        docker("start", name)
        wait_for(
            lambda: docker("exec", name, "test", "-f", "/tmp/initializing", check=False).returncode == 0,
            "the initialization-only socket server",
            name,
        )
        assert docker("exec", name, "sh", "-c", command, check=False).returncode != 0, \
            "Health probe accepted the initialization-only server before TCP startup"
        print("PASS: initialization-only server is not ready", flush=True)

        docker("exec", name, "touch", "/tmp/release-init")
        wait_for(
            lambda: docker("inspect", "--format", "{{.State.Health.Status}}", name).stdout.strip() == "healthy",
            "authenticated TCP readiness",
            name,
        )
        docker("exec", name, "sh", "-c", command)
        for override in ["MYSQL_PASSWORD=incorrect", "MYSQL_DATABASE=missing_database"]:
            assert docker("exec", "-e", override, name, "sh", "-c", command, check=False).returncode != 0, \
                "Health probe accepted invalid application credentials or a missing database"
        print("PASS: TCP startup succeeds; invalid credentials and missing database fail", flush=True)
    except Exception:
        logs = docker("logs", "--tail", "25", name, check=False)
        print(logs.stdout + logs.stderr, flush=True)
        raise
    finally:
        docker("rm", "--force", "--volumes", name, check=False)


if __name__ == "__main__":
    main()
