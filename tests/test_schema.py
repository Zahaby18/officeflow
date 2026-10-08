import re
import unittest
from pathlib import Path

SCHEMA_PATH = Path(__file__).resolve().parents[1] / "database" / "migrations" / "001_initial_schema.sql"


class OfficeFlowSchemaTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.schema = SCHEMA_PATH.read_text(encoding="utf-8")

    def test_creates_core_mvp_tables(self):
        tables = set(re.findall(r"CREATE TABLE IF NOT EXISTS `([^`]+)`", self.schema, re.I))
        self.assertEqual(
            tables,
            {
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
            },
        )

    def test_users_have_admin_and_staff_roles_and_unique_email(self):
        users = re.search(r"CREATE TABLE IF NOT EXISTS `users`\s*\((.*?)\)\s*ENGINE=", self.schema, re.I | re.S)
        self.assertIsNotNone(users)
        definition = users.group(1)
        self.assertRegex(definition, r"`email`\s+VARCHAR\(190\)\s+NOT NULL")
        self.assertRegex(definition, r"UNIQUE KEY `uq_users_email`\s*\(`email`\)")
        self.assertRegex(definition, r"ENUM\('admin',\s*'staff'\)")

    def test_domain_records_are_scoped_to_a_user_or_parent_record(self):
        self.assertIn("CONSTRAINT `fk_requests_requester`", self.schema)
        self.assertIn("CONSTRAINT `fk_request_comments_request`", self.schema)
        self.assertIn("CONSTRAINT `fk_notifications_user`", self.schema)
        self.assertIn("CONSTRAINT `fk_inventory_transactions_item`", self.schema)

    def test_all_tables_use_innodb_and_utf8mb4(self):
        table_definitions = re.findall(
            r"CREATE TABLE IF NOT EXISTS `[^`]+`\s*\(.*?\)\s*ENGINE=([^;]+);",
            self.schema,
            re.I | re.S,
        )
        self.assertEqual(len(table_definitions), 11)
        for definition in table_definitions:
            self.assertRegex(definition, r"InnoDB")
            self.assertRegex(definition, r"utf8mb4")

    def test_schema_is_repeatable_and_does_not_drop_existing_data(self):
        self.assertNotRegex(self.schema, r"DROP\s+TABLE|TRUNCATE\s+TABLE")
        self.assertEqual(self.schema.count("CREATE TABLE IF NOT EXISTS"), 11)


if __name__ == "__main__":
    unittest.main()
