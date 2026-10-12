<?php

declare(strict_types=1);

if (!defined('OFFICEFLOW_APP')) {
    http_response_code(404);
    exit;
}

/**
 * Declarative registry for the record-based office modules.
 *
 * Every module listed here gets list / create / update / delete behaviour from
 * CrudService and one shared screen from module.php, so a new module is a
 * definition rather than a new page. Field names must match real table columns;
 * CrudService refuses anything that is not declared here.
 */
final class Modules
{
    public const SECTIONS = [
        'workspace' => [
            'label' => 'Workspace',
            'items' => ['requests', 'tasks', 'announcements'],
        ],
        'resources' => [
            'label' => 'Office resources',
            'items' => ['assets', 'inventory', 'maintenance', 'rooms', 'vendors', 'documents'],
        ],
        'management' => [
            'label' => 'Management',
            'items' => ['team', 'reports', 'activity', 'settings'],
        ],
    ];

    /** Modules rendered by the shared CRUD screen. */
    public static function crud(): array
    {
        return [
            'assets' => [
                'label' => 'Assets',
                'singular' => 'asset',
                'icon' => 'i-box',
                'description' => 'Know what the office owns, where it is, and when it needs attention.',
                'table' => 'assets',
                'order_by' => 'name ASC',
                'search' => ['asset_tag', 'name', 'category', 'location'],
                'columns' => [
                    'asset_tag' => 'Asset tag',
                    'name' => 'Name',
                    'category' => 'Category',
                    'location' => 'Location',
                    'status' => 'Status',
                ],
                'fields' => [
                    'asset_tag' => ['label' => 'Asset tag', 'type' => 'text', 'required' => true, 'max' => 60],
                    'name' => ['label' => 'Name', 'type' => 'text', 'required' => true, 'max' => 180],
                    'category' => ['label' => 'Category', 'type' => 'text', 'required' => true, 'max' => 80, 'default' => 'Equipment'],
                    'location' => ['label' => 'Location', 'type' => 'text', 'max' => 120],
                    'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'options' => ['active', 'maintenance', 'retired'], 'default' => 'active'],
                    'purchased_on' => ['label' => 'Purchased on', 'type' => 'date'],
                    'warranty_until' => ['label' => 'Warranty until', 'type' => 'date'],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea', 'max' => 5000],
                ],
            ],
            'vendors' => [
                'label' => 'Vendors',
                'singular' => 'vendor',
                'icon' => 'i-users',
                'description' => 'Keep supplier details and service notes close to the work.',
                'table' => 'vendors',
                'order_by' => 'name ASC',
                'search' => ['name', 'service_type', 'contact_name'],
                'columns' => [
                    'name' => 'Vendor',
                    'service_type' => 'Service',
                    'contact_name' => 'Contact',
                    'phone' => 'Phone',
                    'status' => 'Status',
                ],
                'fields' => [
                    'name' => ['label' => 'Vendor name', 'type' => 'text', 'required' => true, 'max' => 180],
                    'service_type' => ['label' => 'Service type', 'type' => 'text', 'required' => true, 'max' => 80, 'default' => 'General'],
                    'contact_name' => ['label' => 'Contact person', 'type' => 'text', 'max' => 120],
                    'phone' => ['label' => 'Phone', 'type' => 'text', 'max' => 40],
                    'email' => ['label' => 'Email', 'type' => 'email', 'max' => 190],
                    'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'options' => ['active', 'inactive'], 'default' => 'active'],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea', 'max' => 5000],
                ],
            ],
            'rooms' => [
                'label' => 'Rooms & Facilities',
                'singular' => 'room',
                'icon' => 'i-building',
                'description' => 'Manage shared rooms, facilities, and their day-to-day readiness.',
                'table' => 'rooms',
                'order_by' => 'name ASC',
                'search' => ['name', 'location', 'kind'],
                'columns' => [
                    'name' => 'Room',
                    'kind' => 'Type',
                    'location' => 'Location',
                    'capacity' => 'Capacity',
                    'status' => 'Status',
                ],
                'fields' => [
                    'name' => ['label' => 'Room name', 'type' => 'text', 'required' => true, 'max' => 140],
                    'kind' => ['label' => 'Type', 'type' => 'select', 'required' => true, 'options' => ['meeting_room', 'facility', 'shared_area'], 'default' => 'meeting_room'],
                    'location' => ['label' => 'Location', 'type' => 'text', 'max' => 120],
                    'capacity' => ['label' => 'Capacity', 'type' => 'number', 'min' => 0, 'max' => 10000],
                    'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'options' => ['available', 'in_use', 'maintenance', 'closed'], 'default' => 'available'],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea', 'max' => 5000],
                ],
            ],
            'maintenance' => [
                'label' => 'Maintenance',
                'singular' => 'maintenance record',
                'icon' => 'i-tool',
                'description' => 'Keep repairs, inspections, and service history in one place.',
                'table' => 'maintenance_records',
                'order_by' => 'scheduled_for IS NULL, scheduled_for ASC, id DESC',
                'search' => ['title', 'notes'],
                'columns' => [
                    'title' => 'Work',
                    'kind' => 'Type',
                    'status' => 'Status',
                    'priority' => 'Priority',
                    'scheduled_for' => 'Scheduled',
                ],
                'fields' => [
                    'title' => ['label' => 'Work title', 'type' => 'text', 'required' => true, 'max' => 180],
                    'asset_id' => ['label' => 'Asset', 'type' => 'reference', 'table' => 'assets', 'display' => 'name', 'optional' => true],
                    'vendor_id' => ['label' => 'Vendor', 'type' => 'reference', 'table' => 'vendors', 'display' => 'name', 'optional' => true],
                    'kind' => ['label' => 'Type', 'type' => 'select', 'required' => true, 'options' => ['repair', 'inspection', 'service'], 'default' => 'repair'],
                    'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'options' => ['open', 'scheduled', 'in_progress', 'done', 'cancelled'], 'default' => 'open'],
                    'priority' => ['label' => 'Priority', 'type' => 'select', 'required' => true, 'options' => ['low', 'normal', 'high', 'urgent'], 'default' => 'normal'],
                    'scheduled_for' => ['label' => 'Scheduled for', 'type' => 'date'],
                    'cost' => ['label' => 'Cost', 'type' => 'number', 'min' => 0, 'step' => '0.01'],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea', 'max' => 5000],
                ],
            ],
            'documents' => [
                'label' => 'Documents & SOPs',
                'singular' => 'document',
                'icon' => 'i-file',
                'description' => 'Give the team one place to find current procedures and references.',
                'table' => 'documents',
                'order_by' => 'title ASC',
                'search' => ['title', 'category', 'body'],
                'columns' => [
                    'title' => 'Document',
                    'category' => 'Category',
                    'version' => 'Version',
                    'status' => 'Status',
                ],
                'fields' => [
                    'title' => ['label' => 'Title', 'type' => 'text', 'required' => true, 'max' => 180],
                    'category' => ['label' => 'Category', 'type' => 'text', 'required' => true, 'max' => 80, 'default' => 'SOP'],
                    'version' => ['label' => 'Version', 'type' => 'text', 'max' => 40],
                    'url' => ['label' => 'Link', 'type' => 'url', 'max' => 500, 'optional' => true],
                    'body' => ['label' => 'Content', 'type' => 'textarea', 'max' => 20000],
                    'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'options' => ['draft', 'published', 'archived'], 'default' => 'published'],
                ],
            ],
            'announcements' => [
                'label' => 'Announcements',
                'singular' => 'announcement',
                'icon' => 'i-megaphone',
                'description' => 'Share important office updates where the whole team can find them.',
                'table' => 'announcements',
                'order_by' => 'id DESC',
                'search' => ['title', 'body'],
                'columns' => [
                    'title' => 'Announcement',
                    'published_at' => 'Published',
                    'expires_at' => 'Expires',
                ],
                'fields' => [
                    'title' => ['label' => 'Title', 'type' => 'text', 'required' => true, 'max' => 180],
                    'body' => ['label' => 'Message', 'type' => 'textarea', 'required' => true, 'max' => 5000],
                    'published_at' => ['label' => 'Publish at', 'type' => 'datetime'],
                    'expires_at' => ['label' => 'Expires at', 'type' => 'datetime'],
                ],
            ],
        ];
    }

    /** Everything that appears in the sidebar, in order. */
    public static function navigation(): array
    {
        return [
            'requests' => ['label' => 'Requests', 'icon' => 'i-inbox', 'href' => '/requests.php'],
            'tasks' => ['label' => 'Tasks & Checklists', 'icon' => 'i-checklist', 'href' => '/tasks.php'],
            'announcements' => ['label' => 'Announcements', 'icon' => 'i-megaphone', 'href' => '/module.php?m=announcements'],
            'assets' => ['label' => 'Assets', 'icon' => 'i-box', 'href' => '/module.php?m=assets'],
            'inventory' => ['label' => 'Inventory', 'icon' => 'i-package', 'href' => '/inventory.php'],
            'maintenance' => ['label' => 'Maintenance', 'icon' => 'i-tool', 'href' => '/module.php?m=maintenance'],
            'rooms' => ['label' => 'Rooms & Facilities', 'icon' => 'i-building', 'href' => '/module.php?m=rooms'],
            'vendors' => ['label' => 'Vendors', 'icon' => 'i-users', 'href' => '/module.php?m=vendors'],
            'documents' => ['label' => 'Documents & SOPs', 'icon' => 'i-file', 'href' => '/module.php?m=documents'],
            'team' => ['label' => 'Team', 'icon' => 'i-users', 'href' => '/team.php'],
            'reports' => ['label' => 'Reports', 'icon' => 'i-chart', 'href' => '/reports.php'],
            'activity' => ['label' => 'Activity log', 'icon' => 'i-clock', 'href' => '/activity.php'],
            'settings' => ['label' => 'Settings', 'icon' => 'i-settings', 'href' => '/settings.php'],
        ];
    }

    public static function find(string $slug): ?array
    {
        $modules = self::crud();
        if (!isset($modules[$slug])) {
            return null;
        }

        $module = $modules[$slug];
        $module['slug'] = $slug;
        return $module;
    }
}
