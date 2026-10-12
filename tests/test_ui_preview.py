import re
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


class OfficeFlowPreviewTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.html = (ROOT / "preview.html").read_text(encoding="utf-8")
        cls.js = (ROOT / "assets" / "js" / "officeflow.js").read_text(encoding="utf-8")

    def test_all_fourteen_sidebar_destinations_are_present(self):
        pages = re.findall(r'<button class="nav-link[^\"]*"[^>]*data-page="([^\"]+)"', self.html)
        self.assertEqual(
            pages,
            [
                "Home",
                "Requests",
                "Tasks & Checklists",
                "Announcements",
                "Assets",
                "Inventory",
                "Maintenance",
                "Rooms & Facilities",
                "Vendors",
                "Documents & SOPs",
                "Team",
                "Reports",
                "Activity log",
                "Settings",
            ],
        )

    def test_local_ui_assets_exist_and_are_linked(self):
        self.assertIn('href="assets/css/officeflow.css"', self.html)
        self.assertIn('src="assets/js/officeflow.js"', self.html)
        self.assertTrue((ROOT / "assets" / "css" / "officeflow.css").is_file())

    def test_preview_discloses_that_interactions_do_not_save_data(self):
        self.assertIn("Request preview submitted. No data has been saved.", self.js)
        self.assertIn("Sample workspace · Demo data only", self.html)

    def test_notification_and_request_dialog_have_accessible_controls(self):
        self.assertIn('aria-controls="notificationPanel"', self.html)
        self.assertIn('role="dialog" aria-modal="true"', self.html)
        self.assertIn("aria-expanded", self.js)


if __name__ == "__main__":
    unittest.main()
