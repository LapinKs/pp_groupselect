<?php
class qtype_groupselect_edit_form extends question_edit_form {
    protected function definition_inner($mform) {
        $mform->addElement('text', 'groupcount', get_string('groupcount', 'qtype_groupselect'));
        $mform->setType('groupcount', PARAM_INT);
        $mform->addRule('groupcount', null, 'required', null, 'client');
    }

    public function qtype() {
        return 'groupselect';
    }
}
