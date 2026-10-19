# Prints the names of the environment variables the module gets.
import json
import os
import sys

json.dump({"variables": sorted(os.environ)}, sys.stdout)
