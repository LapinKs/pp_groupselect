<?php
// general commit

class restore_qtype_ddingroups_plugin extends restore_qtype_plugin {

    /**
     * Returns the paths to be handled by the plugin at question level
     *
     * @return restore_path_element[]
     */
    protected function define_question_plugin_structure(): array {
        $paths = [];

        // This qtype uses question_answers, add them.
        $this->add_question_question_answers($paths);

        // Add own qtype stuff.
        $elename = 'ddingroups';
        $elepath = $this->get_pathfor('/ddingroups'); // We used get_recommended_name() so this works.
        $paths[] = new restore_path_element($elename, $elepath);

        return $paths; // And we return the interesting paths.
    }

    /**
     * Process the qtype/ddingroups element
     *
     * @param array $data
     */
    public function process_ddingroups(array $data): void {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Detect if the question is created or mapped
        // "question" is the XML tag name, not the DB field name.
        $oldquestionid = $this->get_old_parentid('question');
        $newquestionid = $this->get_new_parentid('question');

        // If the question has been created by restore,
        // we need to create a "qtype_ddingroups_options" record
        // and create a mapping from the $oldid to the $newid.
        if ($this->get_mappingid('question_created', $oldquestionid)) {
            $data->questionid = $newquestionid;
            if (!isset($data->shownumcorrect)) {
                $data->shownumcorrect = 1;
            }
            $newid = $DB->insert_record('qtype_ddingroups_options', $data);
            $this->set_mapping('qtype_ddingroups_options', $oldid, $newid);
        }
    }

    /**
     * Given one question_states record, return the answer
     * recoded pointing to all the restored stuff for ddingroups questions.
     * If not empty, answer is one question_answers->id.
     *
     * @param object $state
     * @return string|false
     * @codeCoverageIgnore Restoring from 2.0 is risky business and hopefully not needed.
     */
    public function recode_legacy_state_answer($state): string|false {
        $answer = $state->answer;
        $result = '';
        if ($answer) {
            $result = $this->get_mappingid('question_answer', $answer);
        }
        return $result;
    }
}
