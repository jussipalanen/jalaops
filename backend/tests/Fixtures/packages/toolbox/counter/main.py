import json
import sys

json.dump({"count": len(json.load(sys.stdin))}, sys.stdout)
