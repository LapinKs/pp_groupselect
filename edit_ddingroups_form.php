<?php

defined('MOODLE_INTERNAL') || die();


require_once($CFG->dirroot.'/question/type/ddingroups/question.php');

class qtype_ddingroups_edit_form extends question_edit_form {

    /** Rows count in answer field */
    const TEXTFIELD_ROWS = 2;

    /** Cols count in answer field */
    const TEXTFIELD_COLS = 60;

    /** Number of answers in question by default */
    const NUM_ITEMS_DEFAULT = 1;

    /** Minimum number of answers to show */
    const NUM_ITEMS_MIN = 1;
    /** Number of answers to add on demand */
    const NUM_ITEMS_ADD = 1;
    const GROUPS = 1;
    public function qtype(): string {
        return 'ddingroups';
    }

    /**
     * Plugin name is class name without trailing "_edit_form"
     *
     * @return string
     */
    public function plugin_name(): string {
        return 'qtype_ddingroups';
    }


    public function definition_inner($mform): void {
        global $PAGE;
    
        // Поле для типа макета.
        $options = qtype_ddingroups_question::get_layout_types();
        $mform->addElement('select', 'layouttype', get_string('layouttype', 'qtype_ddingroups'), $options);
        $mform->addHelpButton('layouttype', 'layouttype', 'qtype_ddingroups');
        $mform->setDefault('layouttype', $this->get_default_value('layouttype', qtype_ddingroups_question::LAYOUT_VERTICAL));
    
        // Поле для типа оценивания.
        $options = qtype_ddingroups_question::get_grading_types();
        $mform->addElement('select', 'gradingtype', get_string('gradingtype', 'qtype_ddingroups'), $options);
        $mform->addHelpButton('gradingtype', 'gradingtype', 'qtype_ddingroups');
        $mform->setDefault(
            'gradingtype',
            $this->get_default_value('gradingtype', qtype_ddingroups_question::GRADING_ABSOLUTE_POSITION)
        );
    
        // Поле для отображения оценивания.
        $options = [0 => get_string('hide'), 1 => get_string('show')];
        $mform->addElement('select', 'showgrading', get_string('showgrading', 'qtype_ddingroups'), $options);
        $mform->addHelpButton('showgrading', 'showgrading', 'qtype_ddingroups');
        $mform->setDefault('showgrading', $this->get_default_value('showgrading', 1));
    
        // Добавляем заголовок для групп.
        $mform->addElement('header', 'groupsheader', get_string('groups', 'qtype_ddingroups'));
        $mform->setExpanded('groupsheader', true);
    
        // Поля для групп.
        $elements1 = [];
        $options1 = [];
        $elements1[] = $mform->createElement('text', 'groups', get_string('groupno', 'qtype_ddingroups'));
        $elements1[] = $mform->createElement('html', '<hr>');
        $options1['groups'] = ['type' => PARAM_RAW];
        $this->add_repeat_elements($mform, 'groups', $elements1, $options1);
        $mform->addHelpButton('groups', 'groups', 'qtype_ddingroups');
    

        $groupsf = 0;
        $ggg = [];
        $ggg[]  = $mform->getElement('count'.'groups'.'s')->getValue(); 

        foreach ($ggg as $i) {
            $groupsf += (int)$i;
        }
    
        // Формируем массив групп для select.
        $groupsArray = ['1' => 'Wrong answer'];
        for ($i = 1; $i <= $groupsf; $i++) {
            $groupsArray["$i-1"] = "Group $i"; 
        }
    
        
        
        $mform->addElement('header', 'answersheader', get_string('draggableitems', 'qtype_ddingroups'));
        $mform->setExpanded('answersheader', true);
       
        // Поля для вариантов ответа.
        $elements = [];
        $options = [];
        $elements[] = $mform->createElement('editor', 'answer', get_string('draggableitemno', 'qtype_ddingroups'),
            $this->get_editor_attributes(), $this->get_editor_options());
        $elements[] = $mform->createElement('submit', 'answer' . 'removeeditor', get_string('removeeditor', 'qtype_ddingroups'),
            ['onclick' => 'skipClientValidation = true;']);
        $options['answer'] = ['type' => PARAM_RAW];
        $elements[] = $mform->createElement('select', 'selectgroup', get_string('selectforgroup', 'qtype_ddingroups'), $groupsArray);
        $elements[] = $mform->createElement('html', '<hr>');
        $this->add_repeat_elements($mform, 'answer', $elements, $options);
        // Настройка редакторов HTML.
        $this->adjust_html_editors($mform, 'answer');
    
        // Добавляем поля обратной связи.
        $this->add_combined_feedback_fields(true);
    
        // Добавляем настройки интерактивности.
        $this->add_interactive_settings(false, true);
    }
    
    
    
    protected function add_repeat_elements(MoodleQuickForm $mform, string $type, array $elements, array $options): void {

        // Cache element names.
        $types = $type.'s';
        $addtypes = 'add'.$types;
        $counttypes = 'count'.$types;
        $addtypescount = $addtypes.'count';
        $addtypesgroup = $addtypes.'group';
        
        $repeats = $this->get_answer_repeats($this->question);

        $count = optional_param($addtypescount, self::NUM_ITEMS_ADD, PARAM_INT);
        $label = ($count == 1 ? 'addsingle'.$type : 'addmultiple'.$types);
        $label = get_string($label, 'qtype_ddingroups', $count);

        $this->repeat_elements($elements, $repeats, $options, $counttypes, $addtypes, $count, $label, true);

        // Remove the original "Add xxx" button ...
        $mform->removeElement($addtypes);

        // ... and replace it with "Add" button + select group.
        $options = $this->get_addcount_options($type);
        $mform->addGroup([
            $mform->createElement('submit', $addtypes, get_string('add')),
            $mform->createElement('select', $addtypescount, '', $options),
        ], $addtypesgroup, '', ' ', false);

        // Set default value and type of select element.
        $mform->setDefault($addtypescount, $count);
        $mform->setType($addtypescount, PARAM_INT);
    }
    

    
    
    

    /**
     * Returns answer repeats count
     *
     * @param stdClass $question
     * @return int
     */
    protected function get_answer_repeats(stdClass $question): int {
        if (isset($question->id)) {
            $repeats = count($question->options->answers);
        } else {
            $repeats = self::NUM_ITEMS_DEFAULT;
        }
        if ($repeats < self::NUM_ITEMS_MIN) {
            $repeats = self::NUM_ITEMS_MIN;
        }
        return $repeats;
    }

    /**
     * Returns editor attributes
     *
     * @return array
     */
    protected function get_editor_attributes(): array {
        return [
            'rows' => self::TEXTFIELD_ROWS,
            'cols' => self::TEXTFIELD_COLS,
        ];
    }

    /**
     * Returns editor options
     *
     * @return array
     */
    protected function get_editor_options(): array {
        return [
            'context' => $this->context,
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'noclean' => true,
        ];
    }

    /**
     * Resets editor format to specified
     *
     * @param MoodleQuickForm_editor $editor
     * @param int|string $format
     * @return int
     */
    protected function reset_editor_format(MoodleQuickForm_editor $editor, int|string $format = FORMAT_MOODLE): int {
        $value = $editor->getValue();
        $value['format'] = $format;
        $editor->setValue($value);
        return $format;
    }

    /**
     * Adjust HTML editor and removal buttons.
     *
     * @param MoodleQuickForm $mform
     * @param string $name
     */
    protected function adjust_html_editors(MoodleQuickForm $mform, string $name): void {

        // Cache the number of formats supported
        // by the preferred editor for each format.
        $count = [];

        if (isset($this->question->options->answers)) {
            $ids = array_keys($this->question->options->answers);
        } else {
            $ids = [];
        }

        $defaultanswerformat = get_config('qtype_ddingroups', 'defaultanswerformat');

        $repeats = 'count'.$name.'s'; // E.g. countanswers.
        if ($mform->elementExists($repeats)) {
            // Use mform element to get number of repeats.
            $repeats = $mform->getElement($repeats)->getValue();
        } else {
            // Determine number of repeats by object sniffing.
            $repeats = 0;
            while ($mform->elementExists($name."[$repeats]")) {
                $repeats++;
            }
        }

        for ($i = 0; $i < $repeats; $i++) {
            $editor = $mform->getElement($name."[$i]");
            $id = $ids[$i] ?? 0;

            // The old/new name of the button to remove the HTML editor
            // old : the name of the button when added by repeat_elements
            // new : the simplified name of the button to satisfy "no_submit_button_pressed()" in lib/formslib.php.
            $oldname = $name.'removeeditor['.$i.']';
            $newname = $name.'removeeditor_'.$i;

            // Remove HTML editor, if necessary.
            if (optional_param($newname, 0, PARAM_RAW)) {
                $format = $this->reset_editor_format($editor);
                $_POST['answer'][$i]['format'] = $format; // Overwrite incoming data.
            } else if ($id) {
                $format = $this->question->options->answers[$id]->answerformat;
            } else {
                $format = $this->reset_editor_format($editor, $defaultanswerformat);
            }

            // Check we have a submit button - it should always be there !!
            if ($mform->elementExists($oldname)) {
                if (!isset($count[$format])) {
                    $editor = editors_get_preferred_editor($format);
                    $count[$format] = $editor->get_supported_formats();
                    $count[$format] = count($count[$format]);
                }

                if ($count[$format] > 1) {
                    $mform->removeElement($oldname);
                } else {
                    $submit = $mform->getElement($oldname);
                    $submit->setName($newname);
                }
                $mform->registerNoSubmitButton($newname);
            }
        }
    }

    protected function get_hint_fields($withclearwrong = false, $withshownumpartscorrect = false): array {
        $mform = $this->_form;

        $repeated = [];
        $repeated[] = $mform->createElement('editor', 'hint', get_string('hintn', 'question'),
            ['rows' => 5], $this->editoroptions);
        $repeatedoptions['hint']['type'] = PARAM_RAW;

        $optionelements = [];

        if ($withshownumpartscorrect) {
            $optionelements[] = $mform->createElement('advcheckbox', 'hintshownumcorrect', '',
                get_string('shownumpartscorrect', 'question'));
        }

        $optionelements[] = $mform->createElement('advcheckbox', 'hintoptions', '',
            get_string('highlightresponse', 'qtype_ddingroups'));

        if (count($optionelements)) {
            $repeated[] = $mform->createElement('group', 'hintoptions',
                get_string('hintnoptions', 'question'), $optionelements, null, false);
        }

        return [$repeated, $repeatedoptions];
    }

    public function data_preprocessing($question): stdClass {

        $question = parent::data_preprocessing($question);
        $question = $this->data_preprocessing_answers($question, true);

        // Preprocess feedback.
        $question = $this->data_preprocessing_combined_feedback($question, true);

        $question = $this->data_preprocessing_hints($question, false, true);

        // Preprocess answers and fractions.
        $question->answer = [];
        $question->fraction = [];

        if (empty($question->options->answers)) {
            $answerids = [];
        } else {
            $answerids = array_keys($question->options->answers);
        }

        $defaultanswerformat = get_config('qtype_ddingroups', 'defaultanswerformat');
        $repeats = $this->get_answer_repeats($question);
        for ($i = 0; $i < $repeats; $i++) {

            if ($answerid = array_shift($answerids)) {
                $answer = $question->options->answers[$answerid];
            } else {
                $answer = (object) ['answer' => '', 'answerformat' => $defaultanswerformat];
                $answerid = 0;
            }

            if (empty($question->id)) {
                $question->answer[$i] = $answer->answer;
            } else {
                $itemid = file_get_submitted_draft_itemid("answer[$i]");
                $format = $answer->answerformat;
                $text = file_prepare_draft_area($itemid, $this->context->id, 'question', 'answer',
                    $answerid, $this->editoroptions, $answer->answer);
                $question->answer[$i] = [
                    'text' => $text,
                    'format' => $format,
                    'itemid' => $itemid,
                ];
            }
            $question->fraction[$i] = ($i + 1);
        }

        // Defining default values.
        $names = [
            'layouttype' => qtype_ddingroups_question::LAYOUT_VERTICAL,
            'gradingtype' => qtype_ddingroups_question::GRADING_ABSOLUTE_POSITION,
            'showgrading' => 1,
        ];
        foreach ($names as $name => $default) {
            $question->$name = $question->options->$name ?? $this->get_default_value($name, $default);
        }

        return $question;
    }

    protected function data_preprocessing_hints($question, $withclearwrong = false, $withshownumpartscorrect = false): stdClass {
        if (empty($question->hints)) {
            return $question;
        }
        parent::data_preprocessing_hints($question, $withclearwrong, $withshownumpartscorrect);

        $question->hintoptions = [];
        foreach ($question->hints as $hint) {
            $question->hintoptions[] = $hint->options;
        }

        return $question;
    }

    public function validation($data, $files): array {
        $errors = [];
        

        // Проверка на дубликаты названий групп (только для непустых названий).
        if (!empty($data['groups'])) {
            $groupNames = [];
            foreach ($data['groups'] as $index => $groupName) {
                $groupName = trim($groupName);
                if (!empty($groupName)) { // Проверяем только непустые названия.
                    if (in_array($groupName, $groupNames)) {
                        $errors["groups[$index]"] = get_string('duplicate_groupname', 'qtype_ddingroups', $groupName);
                    } else {
                        $groupNames[] = $groupName;
                    }
                }
            }
        }
    
        // Проверка: нельзя оставить пустой ответ для группы, отличной от "Wrong answer".
        if (!empty($data['answer'])) {
            foreach ($data['answer'] as $index => $answer) {
                $selectedGroup = $data['selectgroup'][$index] ?? '1'; // По умолчанию считаем "Wrong answer".
                if ($selectedGroup !== '1') { // Если выбрана не группа "Wrong answer".
                    if (empty(trim($answer['text'] ?? ''))) {
                        $errors["answer[$index]"] = get_string('answer_empty_for_group', 'qtype_ddingroups');
                    }
                }
            }
        }
    
        // Проверка: названия групп на которые ссылаются варианты ответов.
        if (!empty($data['answer']) && !empty($data['groups'])) {
            foreach ($data['answer'] as $index => $answer) {
                $selectedGroup = $data['selectgroup'][$index] ?? null;
                if ($selectedGroup !== null && $selectedGroup !== '1') { // Если есть ссылка на группу.
                    $groupNameIndex = (int)$selectedGroup - 1; // Индекс группы в массиве `groups`.
                    if (empty(trim($data['groups'][$groupNameIndex] ?? ''))) {
                        $errors["groups[$groupNameIndex]"] = get_string('groupname_empty_referenced', 'qtype_ddingroups');
                    }
                }
            }
        }
    
        // Проверка на дубликаты ответов.
        $answers = [];
        $answercount = 0;
        foreach ($data['answer'] as $index => $answer) {
            if (is_array($answer)) {
                $answer = $answer['text'];
            }
            if ($answer = trim($answer)) {
                if (in_array($answer, $answers)) {
                    $i = array_search($answer, $answers);
                    $item = get_string('draggableitemno', 'qtype_ddingroups');
                    $item = str_replace('{no}', $i + 1, $item);
                    $item = html_writer::link("#id_answer_$i", $item);
                    $a = (object) ['text' => $answer, 'item' => $item];
                    $errors["answer[$index]"] = get_string('duplicatesnotallowed', 'qtype_ddingroups', $a);
                } else {
                    $answers[] = $answer;
                }
                $answercount++;
            }
        }
       
        // Проверка: минимальное количество ответов.

            if ($answercount == 0) {
                $errors['answer[0]'] = get_string('notenoughanswers', 'qtype_ddingroups', 1);
            }
    
        // Если это новый вопрос, обновляем значения по умолчанию.
        if (empty($errors) && empty($data['id'])) {
            $fields = [
                'layouttype', 'selecttype', 'selectcount',
                'gradingtype', 'showgrading', 'numberingstyle',
            ];
            foreach ($fields as $field) {
                if (array_key_exists($field, $data)) {
                    question_bank::get_qtype($this->qtype())->set_default_value($field, $data[$field]);
                }
            }
        }
    
        return $errors;
    }
    
    

    /**
     * Get array of countable item types
     *
     * @param string $type
     * @param int $max
     * @return array (type => description)
     */
    protected function get_addcount_options(string $type, int $max = 10): array {
        // Generate options.
        $options = [];
        for ($i = 1; $i <= $max; $i++) {
            if ($i == 1) {
                $options[$i] = get_string('addsingle'.$type, 'qtype_ddingroups');
            } else {
                $options[$i] = get_string('addmultiple'.$type.'s', 'qtype_ddingroups', $i);
            }
        }
        return $options;
    }


    
}