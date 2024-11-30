<?php


namespace qtype_groupselect\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;

/**
 * Реализация подсистемы конфиденциальности для qtype_groupselect.
 *
 * @package    qtype_groupselect
 * @copyright  
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 или более поздняя
 */
class provider implements 
    \core_privacy\local\metadata\provider, 
    \core_privacy\local\request\user_preference_provider {

    /**
     * Описание данных, которые плагин qtype_groupselect собирает.
     *
     * @param  collection $collection Коллекция метаданных для добавления.
     * @return collection Коллекция с добавленными метаданными.
     */
    public static function get_metadata(collection $collection): collection {

        $collection->add_user_preference('qtype_groupselect_preference1', 'privacy:preference:preference1');
        $collection->add_user_preference('qtype_groupselect_preference2', 'privacy:preference:preference2');
        return $collection;
    }

    /**
     * Экспорт всех пользовательских предпочтений для плагина qtype_groupselect.
     *
     * @param int $userid ID пользователя, чьи данные экспортируются.
     */
    public static function export_user_preferences(int $userid): void {
        // Экспорт предпочтения 1.
        $preference = get_user_preferences('qtype_groupselect_preference1', null, $userid);
        if (null !== $preference) {
            writer::export_user_preference(
                'qtype_groupselect',
                'preference1',
                $preference,
                get_string('privacy:preference:preference1', 'qtype_groupselect')
            );
        }

        // Экспорт предпочтения 2.
        $preference = get_user_preferences('qtype_groupselect_preference2', null, $userid);
        if (null !== $preference) {
            writer::export_user_preference(
                'qtype_groupselect',
                'preference2',
                $preference,
                get_string('privacy:preference:preference2', 'qtype_groupselect')
            );
        }
    }
}
