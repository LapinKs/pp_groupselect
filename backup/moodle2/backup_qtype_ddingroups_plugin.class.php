<?php

class backup_qtype_ddingroups_plugin extends backup_qtype_plugin {

    /**
     * Returns the qtype information to attach to question element
     *
     * @return backup_plugin_element
     */
    protected function define_question_plugin_structure(): backup_plugin_element {

        // Define the virtual plugin element with the condition to fulfill.
        $plugin = $this->get_plugin_element(null, '../../qtype', 'ddingroups');

        // Create one standard named plugin element (the visible container).
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        // Connect the visible container ASAP.
        $plugin->add_child($pluginwrapper);

        // This qtype uses standard question_answers, add them here
        // to the tree before any other information that will use them.
        $this->add_question_question_answers($pluginwrapper);

        // Now create the qtype own structures.
        $fields = ['groupcount',
            'gradingtype', 'showgrading', 
            'correctfeedback', 'correctfeedbackformat',
            'incorrectfeedback', 'incorrectfeedbackformat',
            'partiallycorrectfeedback', 'partiallycorrectfeedbackformat', 'shownumcorrect',
        ];
        $ddingroups = new backup_nested_element('ddingroups', ['id'], $fields);

        // Now the own qtype tree.
        $pluginwrapper->add_child($ddingroups);

        // Set source to populate the data.
        $params = ['questionid' => backup::VAR_PARENTID];
        $ddingroups->set_source_table('qtype_ddingroups_options', $params);

        // Don't need to annotate ids nor files.

        return $plugin;
    }
}
