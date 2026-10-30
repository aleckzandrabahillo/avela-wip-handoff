"""Offline source checks; no database, private environment or provider access."""
import hashlib
import json
from pathlib import Path
import unittest


ROOT = Path(__file__).resolve().parents[1]


def manifest(name):
    return json.loads((ROOT / "database" / name).read_text(encoding="utf-8"))


class CatalogSourceIntegrity(unittest.TestCase):
    def test_reviewed_catalog_bytes(self):
        raw = (ROOT / "database/avela_catalog_20261030.json").read_bytes()
        evidence = manifest("avela_suitability_20261030.json")
        self.assertNotIn(b"\r\n", raw)
        self.assertEqual(hashlib.sha256(raw).hexdigest(), evidence["catalog_sha256"])

    def test_expansion_identity(self):
        initial = manifest("avela_catalog_20261030.json")
        expansion = manifest("avela_catalog_expansion_20261031.json")
        self.assertEqual(initial["catalog_id"], "avela-fictional-20261030")
        self.assertEqual(len(initial["products"]), 100)
        self.assertEqual(expansion["catalog_id"], "avela-fictional-expansion-20261031")
        self.assertEqual(len(expansion["products"]), 113)
        self.assertEqual(len(expansion["image_updates"]), 5)
        self.assertEqual(
            [item["sku"] for item in expansion["products"]],
            [f"AVE-26-{index:04d}" for index in range(101, 214)],
        )

    def test_all_213_reviewed_images(self):
        initial = manifest("avela_catalog_20261030.json")
        expansion = manifest("avela_catalog_expansion_20261031.json")
        repairs = {item["sku"]: item for item in expansion["image_updates"]}
        products = [repairs.get(item["sku"], item) for item in initial["products"]]
        products += expansion["products"]
        self.assertEqual(len({item["sku"] for item in products}), 213)
        public = (ROOT / "public").resolve()
        for item in products:
            with self.subTest(sku=item["sku"]):
                path = (public / item["image_path"]).resolve()
                self.assertTrue(path.is_relative_to(public))
                self.assertTrue(path.is_file())
                self.assertEqual(
                    hashlib.sha256(path.read_bytes()).hexdigest(), item["image_sha256"]
                )


if __name__ == "__main__":
    unittest.main()
