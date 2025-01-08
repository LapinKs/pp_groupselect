<?php


namespace qtype_ddingroups\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;

class provider implements 
    \core_privacy\local\metadata\provider, 
    \core_privacy\local\request\user_preference_provider {

    
    public static function get_metadata(collection $collection): collection {

        $collection->add_user_preference('qtype_ddingroups_preference1', 'privacy:preference:preference1');
        $collection->add_user_preference('qtype_ddingroups_preference2', 'privacy:preference:preference2');
        return $collection;
    }

    
    public static function export_user_preferences(int $userid): void {
        
        $preference = get_user_preferences('qtype_ddingroups_preference1', null, $userid);
        if (null !== $preference) {
            writer::export_user_preference(
                'qtype_ddingroups',
                'preference1',
                $preference,
                get_string('privacy:preference:preference1', 'qtype_ddingroups')
            );
        }

       
        $preference = get_user_preferences('qtype_ddingroups_preference2', null, $userid);
        if (null !== $preference) {
            writer::export_user_preference(
                'qtype_ddingroups',
                'preference2',
                $preference,
                get_string('privacy:preference:preference2', 'qtype_ddingroups')
            );
        }
    }
}
