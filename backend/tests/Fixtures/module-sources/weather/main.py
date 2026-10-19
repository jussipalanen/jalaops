import json
import sys

json.dump({"weather": "aurinkoista", "input": json.load(sys.stdin)}, sys.stdout)
