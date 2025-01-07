<?php
class qtype_ddingroups extends question_type {

    /**
     * Determine if the question type can have HTML answers.
     *
     * @return bool
     * @codeCoverageIgnore
     */
    public function has_html_answers(): bool {
        return true;
    }

    public function extra_question_fields(): array {
        return [
            'qtype_ddingroups_options', 'groupcount', 'gradingtype', 'showgrading', 'layouttype',
        ];
    }
    

    protected function initialise_question_instance(question_definition $question, $questiondata): void {
        parent::initialise_question_instance($question, $questiondata);
    
        // Инициализация ответов (drag items).
        $question->answers = [];
        if (!empty($questiondata->options->dragitems)) {
            foreach ($questiondata->options->dragitems as $item) {
                $question->answers[$item->id] = (object)[
                    'content' => $item->content,
                    'contentformat' => $item->contentformat,
                    'groupid' => $item->groupid,
                ];
            }
        }
    
        // Инициализация групп.
        $question->groups = [];
        if (!empty($questiondata->options->groups)) {
            foreach ($questiondata->options->groups as $group) {
                $question->groups[$group->groupnumber] = (object)[
                    'content' => $group->content,
                    'correctanswers' => $group->correctanswers, // Поле correctanswers из базы данных.
                ];
            }
        }
    
        // Инициализация комбинированной обратной связи (если используется).
        $this->initialise_combined_feedback($question, $questiondata, true);
    }
    
    
    

    public function save_defaults_for_new_questions(stdClass $fromform): void {
        parent::save_defaults_for_new_questions($fromform);
        $this->set_default_value('gradingtype', $fromform->gradingtype);
        $this->set_default_value('showgrading', $fromform->showgrading);
    }
    public function save_question_options($question): bool|stdClass {
        global $DB;
    
        $result = new stdClass();
        $context = $question->context;
    
        // Удаляем старые записи.
        $DB->delete_records('qtype_ddingroups_groups', ['questionid' => $question->id]);
        $DB->delete_records('qtype_ddingroups_items', ['questionid' => $question->id]);
        error_log("Saving question options for question ID: " . $question->id);
        // Инициализируем группы, включая "Wrong Answer".
        $groupids = [];
        $groupCorrectAnswers = [];
    
        // Добавляем группу "Wrong Answer".
        // $wrongAnswerGroup = (object) [
        //     'questionid' => $question->id,
        //     'content' => 'Wrong Answer', // Название группы.
        //     'groupnumber' => 0, // Номер группы "Wrong Answer" всегда 0.
        //     'correctanswers' => 0, // Будет обновлено позже.
        // ];
        // $groupids[0] = $DB->insert_record('qtype_ddingroups_groups', $wrongAnswerGroup);
        // $groupCorrectAnswers[0] = 0; // Инициализируем счётчик правильных ответов.
    
        // Сохраняем остальные группы.
        if (!empty($question->groups)) {
            foreach ($question->groups as $index => $groupname) {
                $groupname = trim($groupname);
                if ($groupname === '') {
                    continue; // Пропускаем пустые ответы.
                }
                $group = (object) [
                    'questionid' => $question->id,
                    'content' => $groupname,
                    'groupnumber' => $index + 1, // Группы начинаются с 1 (после "Wrong Answer").
                    'correctanswers' => 0, // Будет рассчитано позже.
                ];
                $groupids[$index + 1] = $DB->insert_record('qtype_ddingroups_groups', $group);
                $groupCorrectAnswers[$index + 1] = 0; // Инициализируем счётчик правильных ответов.
            }
        }
    
        // Сохраняем элементы для перетаскивания (ответы).
        if (!empty($question->answer)) {
            foreach ($question->answer as $i => $answer) {
                $answertext = trim($answer['text'] ?? '');
                $answerformat = $answer['format'] ?? 0;
                $groupnumber = $question->selectgroup[$i] ?? 0; // Номер группы для ответа.
    
                if ($answertext === '') {
                    continue; // Пропускаем пустые ответы.
                }
                $groupIndex = (int)$groupnumber;
                // Определяем ID группы, к которой принадлежит ответ.
                if (array_key_exists($groupnumber, $groupids)) {
                    $assignedGroupId = $groupids[$groupnumber]; // Найденная группа.
                } else {
                    $assignedGroupId = $groupids[0]; // Если группа не найдена, относим к "Wrong Answer".
                    $groupnumber = 0; // Устанавливаем "Wrong Answer" как группу по умолчанию.
                }
    
                // Увеличиваем счётчик правильных ответов для группы.
                $groupCorrectAnswers[$groupnumber]++;
    
                // Сохраняем элемент в таблицу `qtype_ddingroups_items`.
                $item = (object) [
                    'questionid' => $question->id,
                    'content' => $answertext,
                    'contentformat' => $answerformat,
                    'groupid' => $assignedGroupId, // Привязка к группе.
                    'sortorder' => $i, // Порядок элемента.
                ];
                $DB->insert_record('qtype_ddingroups_items', $item);
            }
        }
    
        // Обновляем поле `correctanswers` для каждой группы.
        foreach ($groupids as $groupnumber => $groupid) {
            $correctCount = $groupCorrectAnswers[$groupnumber] ?? 0;
            $DB->set_field('qtype_ddingroups_groups', 'correctanswers', $correctCount, ['id' => $groupid]);
        }
    
        // Сохраняем настройки вопроса.
        $options = (object) [
            'questionid' => $question->id,
            'groupcount' => count($groupids), // Количество групп (включая "Wrong Answer").
            'gradingtype' => $question->gradingtype,
            'showgrading' => $question->showgrading,
            'layouttype' => $question->layouttype,
        ];
        $options = $this->save_combined_feedback_helper($options, $question, $context, true);
        $this->save_hints($question, true);
    
        // Добавляем или обновляем настройки в таблице `qtype_ddingroups_options`.
        if ($options->id = $DB->get_field('qtype_ddingroups_options', 'id', ['questionid' => $question->id])) {
            $DB->update_record('qtype_ddingroups_options', $options);
        } else {
            unset($options->id);
            $DB->insert_record('qtype_ddingroups_options', $options);
        }
    
        return true;
    }

    
    

    public function get_possible_responses($questiondata): array {
        $responseclasses = [];
        foreach ($questiondata->options->answers as $answer) {
            $groupid = $answer->groupid;
            $responseclasses[$answer->id] = [
                $groupid => new question_possible_response(
                    get_string('belongstogroup', 'qtype_ddingroups', $groupid),
                    1.0 // Правильный ответ.
                ),
            ];
        }
        return $responseclasses;
    }
    
    /**
     * Callback function for filtering answers with array_filter
     *
     * @param mixed $value
     * @return bool If true, this item should be saved.
     */
    public function is_not_blank(mixed $value): bool {
        if (is_array($value)) {
            $value = $value['text'];
        }
        $value = trim($value);
        return ($value || $value === '0');
    }

    public function get_question_options($question): bool {
        global $DB, $OUTPUT;
    
        // Загружаем опции вопроса.
        if (!$question->options = $DB->get_record('qtype_ddingroups_options', ['questionid' => $question->id])) {
            echo $OUTPUT->notification('Error: Missing question options!');
            return false;
        }
    
        // Загружаем элементы для перетаскивания (drag items).
        if (!$question->options->dragitems = $DB->get_records('qtype_ddingroups_items', ['questionid' => $question->id], 'sortorder')) {
            $question->options->dragitems = []; // Если записи отсутствуют, создаём пустой массив.
        }
    
        // Загружаем группы.
        if (!$question->options->groups = $DB->get_records('qtype_ddingroups_groups', ['questionid' => $question->id], 'groupnumber')) {
            $question->options->groups = []; // Если записи отсутствуют, создаём пустой массив.
        }
    
        parent::get_question_options($question);
        return true;
    }
    
    

    public function delete_question($questionid, $contextid): void {
        global $DB;
        $DB->delete_records('qtype_ddingroups_options', ['questionid' => $questionid]);
        $DB->delete_records('qtype_ddingroups_groups', ['questionid' => $questionid]); // Удаляем группы.
        $DB->delete_records('qtype_ddingroups_items', ['questionid' => $questionid]);  // Удаляем элементы.
        parent::delete_question($questionid, $contextid);
    }
    
    /**
     * Import question from GIFT format
     *
     * @param array $lines
     * @param stdClass|null $question
     * @param qformat_gift $format
     * @param string|null $extra (optional, default=null)
     * @return stdClass|bool Question instance
     */
    public function import_from_gift(array $lines, ?stdClass $question, qformat_gift $format, ?string $extra = null): bool|stdClass {
        global $CFG;
        require_once($CFG->dirroot . '/question/type/ddingroups/question.php');
    
        // Проверяем, есть ли дополнительные данные.
        if (!$extra) {
            return false; // Формат данных не распознан.
        }
    
        // Парсинг дополнительных данных.
        $groupcount = '\d+';
        $gradingtype = '(?:ABSOLUTE_POSITION|ABSOLUTE|RELATIVE_TO_CORRECT|RELATIVE)?';
        $layouttype = '(?:HORIZONTAL|VERTICAL|H|V|1|0)?';
        $showgrading = '(?:SHOW|HIDE|TRUE|FALSE|YES|NO|1|0)?';
    
        $search = 
            '('.$groupcount.')\s*' .     // Количество групп.
            '('.$gradingtype.')\s*' .   // Тип оценивания.
            '('.$showgrading.')\s*' .   // Показывать оценивание.
            '('.$layouttype.')\s*' .    // Тип макета.
            '(.*?)\s*$/s';              // Остальные данные (ответы).
    
        if (!preg_match($search, $extra, $matches)) {
            return false; // Данные не соответствуют ожидаемому формату.
        }
    
        $groupcount = (int) trim($matches[1]);
        $gradingtype = trim($matches[2]);
        $showgrading = trim($matches[3]);
        $layouttype = trim($matches[4]);
        $answers_raw = trim($matches[5]);
    
        // Разделяем ответы по строкам.
        $answers = preg_split('/[\r\n]+/', $answers_raw);
        $answers = array_filter(array_map('trim', $answers)); // Удаляем пустые строки.
    
        // Если вопрос ещё не создан, создаём объект.
        if (empty($question)) {
            $text = implode(PHP_EOL, $lines);
            $text = trim($text);
            $name = null;
    
            // Извлечение имени вопроса.
            if (str_starts_with($text, '::')) {
                $text = substr($text, 2);
                $pos = strpos($text, '::');
                if ($pos !== false) {
                    $name = substr($text, 0, $pos);
                    $text = substr($text, $pos + 2);
                }
            }
    
            // Извлечение текста вопроса.
            $questiontext = trim($text);
            $question = new stdClass();
            $question->name = $name ?? 'New question';
            $question->questiontext = $questiontext;
            $question->questiontextformat = FORMAT_HTML; // Формат по умолчанию.
        }
    
        // Устанавливаем основные настройки вопроса.
        $question->qtype = 'ddingroups';
        $question->groupcount = max(2, $groupcount); // Минимум 2 группы.
        $question->gradingtype = strtoupper($gradingtype) === 'RELATIVE' ? 
            qtype_ddingroups_question::GRADING_RELATIVE_TO_CORRECT :
            qtype_ddingroups_question::GRADING_ABSOLUTE_POSITION;
        $question->showgrading = in_array(strtoupper($showgrading), ['SHOW', 'TRUE', 'YES', '1'], true) ? 1 : 0;
        $question->layouttype = strtoupper($layouttype) === 'HORIZONTAL' ? 1 : 0;
    
        // Добавляем группы.
        $question->groups = [];
        $question->groups[0] = 'Wrong Answer'; // Первая группа - "Wrong Answer".
        for ($i = 1; $i <= $question->groupcount; $i++) {
            $question->groups[$i] = "Group $i"; // Имена по умолчанию, можно изменить.
        }
    
        // Привязываем ответы к группам.
        $question->answer = [];
        foreach ($answers as $i => $answer) {
            $parts = explode('|', $answer); // Формат ответа: "Текст|ID группы|Формат".
            $answertext = trim($parts[0]);
            $groupid = isset($parts[1]) ? (int) trim($parts[1]) : 0; // Если группы нет, "Wrong Answer".
            $format = isset($parts[2]) ? strtolower(trim($parts[2])) : 'text'; // Формат по умолчанию.
    
            // Обработка формата.
            $contentformat = match ($format) {
                'html' => FORMAT_HTML,
                'markdown' => FORMAT_MARKDOWN,
                'plain' => FORMAT_PLAIN,
                default => FORMAT_MOODLE, // Формат по умолчанию.
            };
    
            $question->answer[$i] = [
                'text' => $answertext,
                'group' => $groupid,
                'format' => $contentformat,
            ];
        }
    
        return $question;
    }
    
    
    public function extract_options_for_export(stdClass $question): array {
        $gradingtype = match ($question->options->gradingtype) {
            qtype_ddingroups_question::GRADING_ABSOLUTE_POSITION => 'ABSOLUTE_POSITION',
            qtype_ddingroups_question::GRADING_RELATIVE_TO_CORRECT => 'RELATIVE_TO_CORRECT',
            default => '', // Ошибка! Нужно учитывать только допустимые значения.
        };
        $showgrading = match ($question->options->showgrading) {
            0 => 'HIDE',
            1 => 'SHOW',
            default => 'HIDE', // Безопасный дефолт.
        };
        $groupcount = $question->options->groupcount ?? 0; // Обрабатываем отсутствие данных.
        $layouttype = $question->options->layouttype ? 'HORIZONTAL' : 'VERTICAL';
    
        return [$groupcount, $gradingtype, $showgrading, $layouttype];
    }
    /**
     * Exports question to GIFT format
     *
     * @param stdClass $question
     * @param qformat_gift $format
     * @param string|null $extra (optional, default=null)
     * @return string GIFT representation of question
     */
    public function export_to_gift(stdClass $question, qformat_gift $format, ?string $extra = null): string {
        global $CFG;
        require_once($CFG->dirroot.'/question/type/ddingroups/question.php');
    
        $output = '';
    
        if ($question->name) {
            $output .= '::' . $question->name . '::';
        }
    
        $output .= match ($question->questiontextformat) {
            FORMAT_HTML => '[html]',
            FORMAT_PLAIN => '[plain]',
            FORMAT_MARKDOWN => '[markdown]',
            FORMAT_MOODLE => '[moodle]',
            default => '',
        };
    
        $output .= $question->questiontext . '{';
    
        list($groupcount, $gradingtype, $showgrading, $layouttype) = $this->extract_options_for_export($question);
    
        $output .= ">$groupcount $gradingtype $showgrading $layouttype" . PHP_EOL;
    
        // Экспорт групп.
        foreach ($question->options->groups as $group) {
            $output .= 'Group|' . $group->content . PHP_EOL;
        }
    
        // Экспорт ответов.
        foreach ($question->options->answers as $answer) {
            // $groupid = $answer->groupid ?? 0; // Привязка к группе (0 = Wrong Answer).
            $output .= $answer->content . '|' . $groupid . PHP_EOL;
        }
    
        $output .= '}';
        return $output;
    }
    

    public function export_to_xml($question, qformat_xml $format, $extra = null): string {
        global $CFG;
        require_once($CFG->dirroot.'/question/type/ddingroups/question.php');
    
        list($groupcount, $gradingtype, $showgrading, $layouttype) = $this->extract_options_for_export($question);
    
        $output = '';
        $output .= "    <layouttype>$layouttype</layouttype>\n";
        $output .= "    <groupcount>$groupcount</groupcount>\n";
        $output .= "    <gradingtype>$gradingtype</gradingtype>\n";
        $output .= "    <showgrading>$showgrading</showgrading>\n";
        $output .= $format->write_combined_feedback($question->options, $question->id, $question->contextid);
    
        // Экспорт групп.
        foreach ($question->options->groups as $group) {
            $output .= "    <group>\n";
            $output .= "        <content>" . htmlspecialchars($group->content) . "</content>\n";
            $output .= "        <correctanswers>" . $group->correctanswers . "</correctanswers>\n";
            $output .= "    </group>\n";
        }
    
        // Экспорт ответов.
        foreach ($question->options->answers as $answer) {
            $output .= '    <answer groupid="' . ($answer->groupid ?? 0) . '" '
                . $format->format($answer->contentformat) . ">\n";
            $output .= $format->writetext($answer->content, 3);
            $output .= "    </answer>\n";
        }
    
        return $output;
    }
    

    public function import_from_xml($data, $question, qformat_xml $format, $extra = null): object|bool {
        global $CFG;
        require_once($CFG->dirroot.'/question/type/ddingroups/question.php');
    
        $questiontype = $format->getpath($data, ['@', 'type'], '');
    
        if ($questiontype != 'ddingroups') {
            return false;
        }
    
        $newquestion = $format->import_headers($data);
        $newquestion->qtype = $questiontype;
    
        $layouttype = $format->getpath($data, ['#', 'layouttype', 0, '#'], 'VERTICAL') === 'HORIZONTAL' ? 1 : 0;
        $groupcount = (int) $format->getpath($data, ['#', 'groupcount', 0, '#'], 2);
        $gradingtype = $format->getpath($data, ['#', 'gradingtype', 0, '#'], 'RELATIVE');
        $showgrading = $format->getpath($data, ['#', 'showgrading', 0, '#'], '1');
    
        $newquestion->layouttype = $layouttype;
        $newquestion->groupcount = max(2, $groupcount); // Минимум 2 группы.
        $newquestion->gradingtype = strtoupper($gradingtype) === 'RELATIVE' ? 
            qtype_ddingroups_question::GRADING_RELATIVE_TO_CORRECT :
            qtype_ddingroups_question::GRADING_ABSOLUTE_POSITION;
        $newquestion->showgrading = in_array($showgrading, ['SHOW', 'TRUE', 'YES', '1'], true) ? 1 : 0;
    
        $newquestion->groups = [];
        $groupnodes = $format->getpath($data, ['#', 'group'], []);
        foreach ($groupnodes as $groupnode) {
            $newquestion->groups[] = (object) [
                'content' => $format->getpath($groupnode, ['#', 'content', 0, '#'], ''),
                'correctanswers' => (int) $format->getpath($groupnode, ['#', 'correctanswers', 0, '#'], 0),
            ];
        }
    
        $newquestion->answer = [];
        $answernodes = $format->getpath($data, ['#', 'answer'], []);
        foreach ($answernodes as $answernode) {
            $newquestion->answer[] = (object) [
                'content' => $format->getpath($answernode, ['#', 'text', 0, '#'], ''),
                'groupid' => (int) $format->getpath($answernode, ['@', 'groupid'], 0),
            ];
        }
    
        $format->import_combined_feedback($newquestion, $data);
    
        return $newquestion;
    }
    

    /**
     * Set layouttype, selecttype, groupcount, gradingtype, showgrading based on their textual representation
     *
     * @param stdClass $question the question object
     * @param string $showgrading the grading details or not
     */
    public function set_options_for_import(stdClass $question, string $groupcount ,
           string $showgrading, string $gradingtype): void {
            if (is_numeric($groupcount) && $groupcount >= 2) {
                $question->groupcount = intval($groupcount);
            } else {
                $question->groupcount = 2 ; // Default!
            }
            $question->gradingtype = match (strtoupper($gradingtype)) {
                'ABS', 'ABSOLUTE', 'ABSOLUTE_POSITION' => qtype_ddingroups_question::GRADING_ABSOLUTE_POSITION,
                'RELATIVE_TO_CORRECT' => qtype_ddingroups_question::GRADING_RELATIVE_TO_CORRECT,
                default => qtype_ddingroups_question::GRADING_RELATIVE_TO_CORRECT,
            };
        // Set "showgrading" option.
        $question->showgrading = match (strtoupper($showgrading)) {
            'HIDE', 'FALSE', 'NO' => 0,
            default => 1,
        };

        
    }
}