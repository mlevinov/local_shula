<?php
namespace local_shula\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;


/**
 * Privacy Subsystem for local_shula.
 * * Declares the outbound transmission of course structural metadata 
 * to the external Shula AI backend.
 */
class provider implements 
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Returns meta data about this system.
     *
     * @param collection $collection The initialised collection to use.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link(
            'shula_backend',
            [
                'courseid'    => 'privacy:metadata:shula:courseid',
                'fullname'    => 'privacy:metadata:shula:fullname',
                'summary'     => 'privacy:metadata:shula:summary',
                'sections'    => 'privacy:metadata:shula:sections',
                'pagecontent' => 'privacy:metadata:shula:pagecontent',
                'fileurls'    => 'privacy:metadata:shula:fileurls'
            ],
            'privacy:metadata:shula'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     * * Since Shula does not store local user data, we return an empty contextlist.
     *
     * @param int $userid The user to search.
     * @return contextlist Empty contextlist.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        return new contextlist();
    }
}