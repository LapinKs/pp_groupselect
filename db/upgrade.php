<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Plugin upgrade script.
 *
 * @package    {your_plugin_name}
 * @copyright  {year} {your_name} ({your_email})
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade code for the plugin.
 *
 * @param int $oldversion the version we are upgrading from.
 * @return bool true if the upgrade was successful
 */
function xmldb_qtype_groupselect_upgrade($oldversion) {
    global $DB, $dbman;

    // Get the database manager object.
    $dbman = $DB->get_manager();

    // Example: Add a new table during an upgrade.
    if ($oldversion < 2023110101) {
        // Define table {your_table_name}.
        $table = new xmldb_table('{your_table_name}');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('created_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Create the table if it does not exist.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Upgrade savepoint.
        upgrade_plugin_savepoint(true, 2023110101, '{plugin_type}', '{plugin_name}');
    }

    // Example: Add a new field to an existing table.
    if ($oldversion < 2023110201) {
        $table = new xmldb_table('{your_table_name}');
        $field = new xmldb_field('is_active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'description');

        // Add the field if it does not exist.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Upgrade savepoint.
        upgrade_plugin_savepoint(true, 2023110201, '{plugin_type}', '{plugin_name}');
    }

    // Example: Update data in an existing table.
    if ($oldversion < 2023110301) {
        // Update the 'is_active' field for all records in {your_table_name}.
        $DB->set_field('{your_table_name}', 'is_active', 1, []);

        // Upgrade savepoint.
        upgrade_plugin_savepoint(true, 2023110301, '{plugin_type}', '{plugin_name}');
    }

    return true;
}
