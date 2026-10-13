import re
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SCHEMA_PATH = ROOT / "officeflow-schema.sql"

EXPECTED_TABLES = {
    "users",
    "office_settings",
    "requests",
    "request_comments",
    "tasks",
    "assets",
    "inventory_items",
    "inventory_transactions",
    "announcements",
    "notifications",
    "activity_logs",
    "vendors",
    "maintenance_records",
    "rooms",
    "documents",
}


class OfficeFlowSchemaTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.schema = SCHEMA_PATH.read_text(encoding="utf-8")

    def test_single_schema_file_creates_every_table(self):
        tables = set(re.findall(r"CREATE TABLE IF NOT EXISTS `([^`]+)`", self.schema, re.I))
        self.assertEqual(tables, EXPECTED_TABLES)
        self.assertEqual(self.schema.count("CREATE TABLE IF NOT EXISTS"), 15)

    def test_users_have_admin_and_staff_roles_and_unique_email(self):
        users = re.search(r"CREATE TABLE IF NOT EXISTS `users`\s*\((.*?)\)\s*ENGINE=", self.schema, re.I | re.S)
        self.assertIsNotNone(users)
        definition = users.group(1)
        self.assertRegex(definition, r"`email`\s+VARCHAR\(190\)\s+NOT NULL")
        self.assertRegex(definition, r"UNIQUE KEY `uq_users_email`\s*\(`email`\)")
        self.assertRegex(definition, r"ENUM\('admin',\s*'staff'\)")

    def test_domain_records_are_scoped_to_a_user_or_parent_record(self):
        for constraint in (
            "CONSTRAINT `fk_requests_requester`",
            "CONSTRAINT `fk_request_comments_request`",
            "CONSTRAINT `fk_notifications_user`",
            "CONSTRAINT `fk_inventory_transactions_item`",
            "CONSTRAINT `fk_maintenance_asset`",
            "CONSTRAINT `fk_maintenance_vendor`",
            "CONSTRAINT `fk_vendors_creator`",
            "CONSTRAINT `fk_rooms_creator`",
            "CONSTRAINT `fk_documents_creator`",
        ):
            self.assertIn(constraint, self.schema)

    def test_a_referenced_table_is_created_before_it_is_referenced(self):
        # maintenance_records has a foreign key to vendors, so vendors must come first.
        self.assertLess(
            self.schema.index("CREATE TABLE IF NOT EXISTS `vendors`"),
            self.schema.index("CREATE TABLE IF NOT EXISTS `maintenance_records`"),
        )

    def test_all_tables_use_innodb_and_utf8mb4(self):
        definitions = re.findall(
            r"CREATE TABLE IF NOT EXISTS `[^`]+`\s*\(.*?\)\s*ENGINE=([^;]+);",
            self.schema,
            re.I | re.S,
        )
        self.assertEqual(len(definitions), 15)
        for definition in definitions:
            self.assertRegex(definition, r"InnoDB")
            self.assertRegex(definition, r"utf8mb4")

    def test_schema_is_repeatable_and_does_not_drop_existing_data(self):
        self.assertNotRegex(self.schema, r"DROP\s+TABLE|TRUNCATE\s+TABLE")


if __name__ == "__main__":
    unittest.main()
