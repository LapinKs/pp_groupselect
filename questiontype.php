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
            'qtype_ddingroups_options','groupcount','gradingtype', 'showgrading',
        ];
    }

    protected function initialise_question_instance(question_definition $question, $questiondata): void {
        global $CFG;

        parent::initialise_question_instance($question, $questiondata);

        $question->answers = $questiondata->options->answers;
        foreach ($question->answers as $answerid => $answer) {
            $question->answers[$answerid]->md5key = 'ddingroups_item_' . md5(($CFG->passwordsaltmain ?? '') . $answer->answer);
        }

        $this->initialise_combined_feedback($question, $questiondata, true);
    }

    public function save_defaults_for_new_questions(stdClass $fromform): void {
        parent::save_defaults_for_new_questions($fromform);
        $this->set_default_value('groupcount', $fromform->groupcount);
        $this->set_default_value('gradingtype', $fromform->gradingtype);
        $this->set_default_value('showgrading', $fromform->showgrading);
    }

    public function save_question_options($question): bool|stdClass {
        global $DB;
    
        $result = new stdClass();
        $context = $question->context;
    
        // Remove empty answers.
        $question->answer = array_filter($question->answer, [$this, 'is_not_blank']);
        $question->answer = array_values($question->answer); // Make keys sequential.
    
        // Count how many answers we have.
        $countanswers = count($question->answer);
    
        // Check at least two answers exist.
        if ($countanswers < 2) {
            $result->notice = get_string('notenoughanswers', 'qtype_ddingroups', '2');
            return $result;
        }
    
        // Prepare feedback placeholders.
        $question->feedback = range(1, $countanswers);
    
        if ($answerids = $DB->get_records('question_answers', ['question' => $question->id], 'id ASC', 'id,question')) {
            $answerids = array_keys($answerids);
        } else {
            $answerids = [];
        }
    
        // Insert all the new answers.
        foreach ($question->answer as $i => $answer) {
            $answertext = $answer['text'] ?? '';
            $answerformat = $answer['format'] ?? 0;
            $answeritemid = $answer['itemid'] ?? null;
    
            // Skip empty answers.
            if (trim($answertext) === '') {
                continue;
            }
    
            // Prepare the $answer object.
            $answer = (object) [
                'question' => $question->id,
                'fraction' => ($i + 1),
                'answer' => $answertext,
                'answerformat' => $answerformat,
                'feedback' => '',
                'feedbackformat' => FORMAT_MOODLE,
            ];
    
            // Add/insert $answer into the database.
            if ($answer->id = array_shift($answerids)) {
                $DB->update_record('question_answers', $answer);
            } else {
                unset($answer->id);
                $answer->id = $DB->insert_record('question_answers', $answer);
            }
    
            // Copy files across from draft files area.
            if ($answeritemid) {
                $answertext = file_save_draft_area_files(
                    $answeritemid, $context->id, 'question', 'answer', $answer->id,
                    $this->fileoptions, $answertext
                );
                $DB->set_field('question_answers', 'answer', $answertext, ['id' => $answer->id]);
            }
    
            // Save drag items to qtype_ddingroups_items table.
            if (!empty($question->dragitems)) {
                foreach ($question->dragitems as $item) {
                    $item->questionid = $question->id;
    
                    if (empty($item->id)) {
                        $DB->insert_record('qtype_ddingroups_items', $item);
                    } else {
                        $DB->update_record('qtype_ddingroups_items', $item);
                    }
                }
            }
        }
    
        // Create $options for this ddingroups question.
        $options = (object) [
            'questionid' => $question->id,
            'groupcount' => $question->groupcount,
            'gradingtype' => $question->gradingtype,
            'showgrading' => $question->showgrading,
        ];
        $options = $this->save_combined_feedback_helper($options, $question, $context, true);
        $this->save_hints($question, true);
    
        // Add/update $options for this ddingroups question.
        if ($options->id = $DB->get_field('qtype_ddingroups_options', 'id', ['questionid' => $question->id])) {
            $DB->update_record('qtype_ddingroups_options', $options);
        } else {
            unset($options->id);
            $DB->insert_record('qtype_ddingroups_options', $options);
        }
    
        // Delete old answer records, if any.
        if (count($answerids)) {
            $fs = get_file_storage();
            foreach ($answerids as $answerid) {
                $fs->delete_area_files($context->id, 'question', 'answer', $answerid);
                $DB->delete_records('question_answers', ['id' => $answerid]);
            }
        }
    
        return true;
    }

    public function get_possible_responses($questiondata): array {
        $responseclasses = [];
        $itemcount = count($questiondata->options->answers);

        $position = 0;
        foreach ($questiondata->options->answers as $answer) {
            $position += 1;
            $classes = [];
            for ($i = 1; $i <= $itemcount; $i++) {
                $classes[$i] = new question_possible_response(
                    get_string('positionx', 'qtype_ddingroups', $i),
                    ($i === $position) / $itemcount);
            }

            $subqid = question_utils::to_plain_text($answer->answer, $answer->answerformat);
            $subqid = core_text::substr($subqid, 0, 100); // Ensure not more than 100 chars.
            $responseclasses[$subqid] = $classes;
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
    
        // Load the options.
        if (!$question->options = $DB->get_record('qtype_ddingroups_options', ['questionid' => $question->id])) {
            echo $OUTPUT->notification('Error: Missing question options!');
            return false;
        }
    
        // Load the answers.
        if (!$question->options->answers = $DB->get_records('question_answers', ['question' => $question->id], 'fraction, id')) {
            echo $OUTPUT->notification('Error: Missing question answers for ddingroups question ' . $question->id . '!');
            return false;
        }
    
        // Load drag items.
        $question->options->dragitems = $DB->get_records('qtype_ddingroups_items', ['questionid' => $question->id]);
    
        parent::get_question_options($question);
        return true;
    }

    public function delete_question($questionid, $contextid): void {
        global $DB;
    
        // Delete associated options.
        $DB->delete_records('qtype_ddingroups_options', ['questionid' => $questionid]);
    
        // Delete drag items.
        $DB->delete_records('qtype_ddingroups_items', ['questionid' => $questionid]);
    
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
        require_once($CFG->dirroot.'/question/type/ddingroups/question.php');

        // Extract question info from GIFT file $lines.
        $groupcount = '\d+';
        $gradingtype = '(?:ABSOLUTE_POSITION|'.
            'ABSOLUTE|ABS|'.
            'RELATIVE_TO_CORRECT|'.
            'RELATIVE|REL)?';
        $showgrading = '(?:SHOW|TRUE|YES|1|HIDE|FALSE|NO|0)?';
        $search = 
            '('.$groupcount.')\s*'.
            '('.$gradingtype.')\s*'.
            '('.$showgrading.')\s*'.
            '(.*?)\s*$/s';
        // Item $1 the number of items to be shown.
        // Item $2 the extraction/grading type.
        // Item $3 the layout type.
        // Item $4 the grading type.
        // Item $5 show the grading details (SHOW/HIDE).
        // Item $6 the numbering style (none/123/abc/...).
        // Item $7 the lines of items to be ordered.
        if (!$extra) {
            return false; // Format not recognized.
        }
        if (!preg_match($search, $extra, $matches)) {
            return false; // Format not recognized.
        }
        $groupcount = trim($matches[1]);
        $gradingtype = trim($matches[2]);
        $showgrading = trim($matches[3]);

        $answers = preg_split('/[\r\n]+/', $matches[4]);
        $answers = array_filter($answers);

        if (empty($question)) {
            $text = implode(PHP_EOL, $lines);
            $text = trim($text);
            if ($pos = strpos($text, '{')) {
                $text = substr($text, 0, $pos);
            }

            // Extract name.
            $name = false;
            if (str_starts_with($text, '::')) {
                $text = substr($text, 2);
                $pos = strpos($text, '::');
                if (is_numeric($pos)) {
                    $name = substr($text, 0, $pos);
                    $name = $format->clean_question_name($name);
                    $text = trim(substr($text, $pos + 2));
                }
            }

            // Extract question text format.
            $format = FORMAT_MOODLE;
            if (str_starts_with($text, '[')) {
                $text = substr($text, 1);
                $pos = strpos($text, ']');
                if (is_numeric($pos)) {
                    $format = substr($text, 0, $pos);
                    switch ($format) {
                        case 'html':
                            $format = FORMAT_HTML;
                            break;
                        case 'plain':
                            $format = FORMAT_PLAIN;
                            break;
                        case 'markdown':
                            $format = FORMAT_MARKDOWN;
                            break;
                        case 'moodle':
                            $format = FORMAT_MOODLE;
                            break;
                    }
                    $text = trim(substr($text, $pos + 1)); // Remove name from text.
                }
            }

            $question = new stdClass();
            $question->name = $name;
            $question->questiontext = $text;
            $question->questiontextformat = $format;
            $question->generalfeedback = '';
            $question->generalfeedbackformat = FORMAT_MOODLE;
        }

        $question->qtype = 'ddingroups';

        if (is_numeric($groupcount) && $groupcount >= 2) {
            $question->groupcount = intval($groupcount);
        } else {
            $question->groupcount = 2 ; // Default!
        }
        $this->set_options_for_import($question, $groupcount,  
            $showgrading, $gradingtype);

        // Remove blank items.
        $answers = array_map('trim', $answers);
        $answers = array_filter($answers); // Remove blanks.

        // Set up answer arrays.
        $question->answer = [];
        $question->answerformat = [];
        $question->fraction = [];
        $question->feedback = [];
        $question->feedbackformat = [];

        // Note that "fraction" field is used to denote sort order
        // "fraction" fields will be set to correct values later
        // in the save_question_options() method of this class.

        foreach ($answers as $i => $answer) {
            $question->answer[$i] = $answer;
            $question->answerformat[$i] = FORMAT_MOODLE;
            $question->fraction[$i] = 1; // Will be reset later in save_question_options().
            $question->feedback[$i] = '';
            $question->feedbackformat[$i] = FORMAT_MOODLE;
        }
        return $question;
    }/**
     * Given question object, returns array with array layouttype, selecttype, groupcount, gradingtype, showgrading
     * where layouttype, selecttype, gradingtype and showgrading are string representations.
     *
     * @param stdClass $question
     * @return array(layouttype, selecttype, groupcount, gradingtype, $showgrading, $numberingstyle)
     */
    public function extract_options_for_export(stdClass $question): array {
        $gradingtype = match ($question->options->gradingtype) {
            qtype_ddingroups_question::GRADING_ABSOLUTE_POSITION => 'ABSOLUTE_POSITION',
            qtype_ddingroups_question::GRADING_RELATIVE_TO_CORRECT => 'RELATIVE_TO_CORRECT',
            default => '', // Shouldn't happen !!
        };
        $showgrading = match ($question->options->showgrading) {
            0 => 'HIDE',
            1 => 'SHOW',
            default => '', // Shouldn't happen !!
        };
        $groupcount = $question->options->groupcount;
        return [ $groupcount, $gradingtype, $showgrading];
    }/**
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
            $output .= '::'.$question->name.'::';
        }

        $output .= match ($question->questiontextformat) {
            FORMAT_HTML => '[html]',
            FORMAT_PLAIN => '[plain]',
            FORMAT_MARKDOWN => '[markdown]',
            FORMAT_MOODLE => '[moodle]',
            default => '',
        };

        $output .= $question->questiontext.'{';

        list($groupcount ,$gradingtype,$showgrading) =
            $this->extract_options_for_export($question);
        $output .= ">$groupcount $gradingtype $showgrading ".PHP_EOL;

        foreach ($question->options->answers as $answer) {
            $output .= $answer->answer.PHP_EOL;
        }

        $output .= '}';
        return $output;
    }

    public function export_to_xml($question, qformat_xml $format, $extra = null): string {
        global $CFG;
        require_once($CFG->dirroot.'/question/type/ddingroups/question.php');

        list($gradingtype,$showgrading) =
            $this->extract_options_for_export($question);

        $output = '';
        $output .= "    <groupcount>$groupcount</groupcount>\n";
        $output .= "    <gradingtype>$gradingtype</gradingtype>\n";
        $output .= "    <showgrading>$showgrading</showgrading>\n";
        $output .= $format->write_combined_feedback($question->options, $question->id, $question->contextid);

        $shownumcorrect = $question->options->shownumcorrect;
        if (!empty($question->options->shownumcorrect)) {
            $output = str_replace("    <shownumcorrect/>\n", "", $output);
        }
        $output .= "    <shownumcorrect>$shownumcorrect</shownumcorrect>\n";

        foreach ($question->options->answers as $answer) {
            $output .= '    <answer fraction="'.$answer->fraction.'" '.$format->format($answer->answerformat).">\n";
            $output .= $format->writetext($answer->answer, 3);
            if (trim($answer->feedback)) { // Usually there is no feedback.
                $output .= '      <feedback '.$format->format($answer->feedbackformat).">\n";
                $output .= $format->writetext($answer->feedback, 4);
                $output .= $format->write_files($answer->feedbackfiles);
                $output .= "      </feedback>\n";
            }
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
    
        $groupcount = $format->getpath($data, ['#', 'groupcount', 0, '#'], 2);
        $gradingtype = $format->getpath($data, ['#', 'gradingtype', 0, '#'], 'RELATIVE');
        $showgrading = $format->getpath($data, ['#', 'showgrading', 0, '#'], '1');
        $this->set_options_for_import($newquestion, $groupcount, $gradingtype, $showgrading);
    
        $newquestion->answer = [];
        $newquestion->dragitems = [];
    
        $i = 0;
        while ($answer = $format->getpath($data, ['#', 'answer', $i], '')) {
            $ans = $format->import_answer($answer, true, $format->get_format($newquestion->questiontextformat));
            $newquestion->answer[$i] = $ans->answer;
            $newquestion->fraction[$i] = 1;
            $newquestion->feedback[$i] = $ans->feedback;
            $i++;
        }
    
        $j = 0;
        while ($dragitem = $format->getpath($data, ['#', 'dragitem', $j], '')) {
            $item = (object) [
                'content' => $dragitem['content'],
                'groupid' => $dragitem['groupid'],
            ];
            $newquestion->dragitems[$j] = $item;
            $j++;
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