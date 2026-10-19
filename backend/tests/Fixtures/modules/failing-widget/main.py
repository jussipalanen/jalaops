# Fails, so its widget shows an error.
import sys

print("Jokin meni pieleen.", file=sys.stderr)
sys.exit(1)
