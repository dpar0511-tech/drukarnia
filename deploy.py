import subprocess
import os

try:
    subprocess.run(["git", "push", "origin", "feature/render-docker-deploy"], check=True)
except Exception as e:
    print(f"Error: {e}")
