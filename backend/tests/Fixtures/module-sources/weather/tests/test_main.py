import json
import subprocess
import sys
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent


class MainTest(unittest.TestCase):
    def test_it_answers(self):
        result = subprocess.run([sys.executable, "main.py"], input="{}", capture_output=True, text=True, cwd=ROOT)
        self.assertEqual(json.loads(result.stdout)["weather"], "aurinkoista")
