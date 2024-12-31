<?php

defined('MOODLE_INTERNAL') || die();


require_once($CFG->dirroot.'/question/type/ddingroups/question.php');

class qtype_ddingroups_edit_form extends question_edit_form {

    /** Rows count in answer field */
    const TEXTFIELD_ROWS = 2;

    /** Cols count in answer field */
    const TEXTFIELD_COLS = 60;

    /** Number of answers in question by default */
    const NUM_ITEMS_DEFAULT = 6;

    /** Minimum number of answers to show */
    const NUM_ITEMS_MIN = 3;

    /** Number of answers to add on demand */
    const NUM_ITEMS_ADD = 1;

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
        // Максимальное количество групп.
        $maxGroups = 9;
    
        // Скрытое поле для хранения текущего количества видимых групп.
        $mform->addElement('hidden', 'groupcount', 1);
        $mform->setType('groupcount', PARAM_INT);
    
        // Получаем текущее количество видимых групп.
        $groupCount = optional_param('groupcount', 1, PARAM_INT);
    
        // Если нажата кнопка "Добавить группу", увеличиваем количество видимых групп.
        if ($this->is_add_group_pressed() && $groupCount < $maxGroups) {
            $groupCount++;
        }
    
        // Обновляем значение groupcount.
        $mform->setDefault('groupcount', $groupCount);
    
        // Поля для настроек оценивания.
        $options = qtype_ddingroups_question::get_grading_types();
        $mform->addElement('select', 'gradingtype', get_string('gradingtype', 'qtype_ddingroups'), $options);
        $mform->addHelpButton('gradingtype', 'gradingtype', 'qtype_ddingroups');
        $mform->setDefault('gradingtype', qtype_ddingroups_question::GRADING_ABSOLUTE_POSITION);
    
        $options = [0 => get_string('hide'), 1 => get_string('show')];
        $mform->addElement('select', 'showgrading', get_string('showgrading', 'qtype_ddingroups'), $options);
        $mform->addHelpButton('showgrading', 'showgrading', 'qtype_ddingroups');
        $mform->setDefault('showgrading', 1);
    
        // Заголовок для групп.
        $mform->addElement('header', 'groupsheader', get_string('groups', 'qtype_ddingroups'));
        $mform->setExpanded('groupsheader', true);
    
        // Добавляем заранее инициализированные группы.
        $this->add_group_elements($mform, $groupCount, $maxGroups);
    
        // Кнопка для добавления новой группы.
        if ($groupCount < $maxGroups) {
            $mform->addElement('submit', 'addgroup', get_string('addgroup', 'qtype_ddingroups'));
            $mform->registerNoSubmitButton('addgroup');
        }
    
        // Поля для обратной связи.
        $this->add_combined_feedback_fields(true);
    
        // Настройки интерактивности.
        $this->add_interactive_settings(false, true);
    }
    
    protected function add_group_elements(MoodleQuickForm $mform, int $groupCount, int $maxGroups): void {
        for ($i = 0; $i < $maxGroups; $i++) {
            // Поле для группы создается всегда, но отображается только если $i < $groupCount.
            $groupVisible = ($i < $groupCount);
    
            // Заголовок группы.
            $mform->addElement('header', "groupheader[$i]", get_string('group', 'qtype_ddingroups') . ' ' . ($i + 1));
            $mform->setExpanded("groupheader[$i]", $groupVisible);
            if (!$groupVisible) {
                $mform->hideIf("groupheader[$i]", 'groupcount', 'lt', $i + 1);
            }
    
            // Поле для названия группы.
            $mform->addElement('text', "groupname[$i]", get_string('groupname', 'qtype_ddingroups'), ['size' => 50]);
            $mform->setType("groupname[$i]", PARAM_TEXT);
            $mform->addHelpButton("groupname[$i]", 'groupname', 'qtype_ddingroups');
            if (!$groupVisible) {
                $mform->hideIf("groupname[$i]", 'groupcount', 'lt', $i + 1);
            }
    
            // Добавляем варианты ответа для группы.
            $this->add_group_answers($mform, $i, $groupVisible);
        }
    }
    
    protected function add_repeat_elements(
        MoodleQuickForm $mform,
        string $type,
        array $elements,
        array $options
    ): void {
        $types = $type . 's';
        $addTypes = 'add' . $types;
        $countTypes = 'count' . $types;
        $addTypesCount = $addTypes . 'count';
        $addTypesGroup = $addTypes . 'group';
    
        // Количество повторений для вариантов.
        $repeats = optional_param($countTypes, self::NUM_ITEMS_MIN, PARAM_INT);
    
        // Кнопка для добавления новых вариантов.
        $count = optional_param($addTypesCount, self::NUM_ITEMS_ADD, PARAM_INT);
        $label = ($count == 1 ? 'addsingle' . $type : 'addmultiple' . $types);
        $label = get_string($label, 'qtype_ddingroups', $count);
    
        // Добавляем повторяющиеся элементы.
        $this->repeat_elements($elements, $repeats, $options, $countTypes, $addTypes, $count, $label, true);
    
        // Удаляем стандартную кнопку "Add xxx".
        $mform->removeElement($addTypes);
    
        // Добавляем группу с кнопкой и выбором количества.
        $addOptions = $this->get_addcount_options($type);
        $mform->addGroup([
            $mform->createElement('submit', $addTypes, get_string('add')),
            $mform->createElement('select', $addTypesCount, '', $addOptions),
        ], $addTypesGroup, '', ' ', false);
    
        // Устанавливаем значение по умолчанию.
        $mform->setDefault($addTypesCount, $count);
        $mform->setType($addTypesCount, PARAM_INT);
    }
    protected function add_group_answers(MoodleQuickForm $mform, int $groupIndex, bool $groupVisible): void {
        // Элементы для вариантов ответа.
        $elements = [];
        $options = [];
    
        $elements[] = $mform->createElement(
            'text',
            "answer[$groupIndex][]",
            get_string('draggableitemno', 'qtype_ddingroups'),
            ['size' => 60]
        );
        $options["answer[$groupIndex][]"] = ['type' => PARAM_RAW];
    
        // Добавляем повторяющиеся элементы для текущей группы.
        $this->add_repeat_elements($mform, "answer[$groupIndex]", $elements, $options);
    
        // Если группа скрыта, скрываем также варианты ответов.
        if (!$groupVisible) {
            foreach ($elements as $element) {
                $mform->hideIf($element->getName(), 'groupcount', 'lt', $groupIndex + 1);
            }
        }
    }
    
    protected function is_add_group_pressed(): bool {
        return optional_param('addgroup', false, PARAM_BOOL);
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
            'selecttype' => qtype_ddingroups_question::SELECT_ALL,
            'selectcount' => qtype_ddingroups_question::MIN_SUBSET_ITEMS,
            'gradingtype' => qtype_ddingroups_question::GRADING_ABSOLUTE_POSITION,
            'showgrading' => 1,  // 1 means SHOW.
            'numberingstyle' => qtype_ddingroups_question::NUMBERING_STYLE_DEFAULT,
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

        $minsubsetitems = qtype_ddingroups_question::MIN_SUBSET_ITEMS;
        // Make sure the entered size of the subset is no less than the defined minimum.
        if ($data['selecttype'] != qtype_ddingroups_question::SELECT_ALL && $data['selectcount'] < $minsubsetitems) {
            $errors['selectcount'] = get_string('notenoughsubsetitems', 'qtype_ddingroups', $minsubsetitems);
        }

        // Identify duplicates and report as an error.
        $answers = [];
        $answercount = 0;
        foreach ($data['answer'] as $answer) {
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
                    $errors["answer[$answercount]"] = get_string('duplicatesnotallowed', 'qtype_ddingroups', $a);
                } else {
                    $answers[] = $answer;
                }
                $answercount++;
            }
        }

        // If there are no answers provided, show error message under first 2 answer boxes
        // If only 1 answer provided, show error message under second answer box.
        if ($answercount < 2) {
            $errors['answer[1]'] = get_string('notenoughanswers', 'qtype_ddingroups', 2);

            if ($answercount == 0) {
                $errors['answer[0]'] = get_string('notenoughanswers', 'qtype_ddingroups', 2);
            }
        }

        // If adding a new ddingroups question, update defaults.
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