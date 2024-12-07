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
            'qtype_ddingroups_options','gradingtype', 'showgrading',
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

        // Search/replace strings to reduce simple <p>...</p> to plain text.
        $psearch = '/^\s*<p>\s*(.*?)(\s*<br\s*\/?>)*\s*<\/p>\s*$/';
        $preplace = '$1';

        // Search/replace strings to standardize vertical align of <img> tags.
        $imgsearch = '/(<img[^>]*)\bvertical-align:\s*[a-zA-Z0-9_-]+([^>]*>)/';
        $imgreplace = '$1'.'vertical-align:text-top'.'$2';

        // Check at least two answers exist.
        if ($countanswers < 2) {
            $result->notice = get_string('notenoughanswers', 'qtype_ddingroups', '2');
            return $result;
        }

        $question->feedback = range(1, $countanswers);

        if ($answerids = $DB->get_records('question_answers', ['question' => $question->id], 'id ASC', 'id,question')) {
            $answerids = array_keys($answerids);
        } else {
            $answerids = [];
        }

        // Insert all the new answers.
        foreach ($question->answer as $i => $answer) {
            $answertext = '';
            $answerformat = 0;
            $answeritemid = null;

            // Extract $answer fields.
            if (is_string($answer)) {
                // Import from file.
                $answertext = $answer;
            } else if (is_array($answer)) {
                // Input from browser.
                if (isset($answer['text'])) {
                    $answertext = $answer['text'];
                }
                if (isset($answer['format'])) {
                    $answerformat = $answer['format'];
                }
                if (isset($answer['itemid'])) {
                    $answeritemid = $answer['itemid'];
                }
            }

            // Reduce simple <p>...</p> to plain text.
            if (substr_count($answertext, '<p>') == 1) {
                $answertext = preg_replace($psearch, $preplace, $answertext);
            }
            $answertext = trim($answertext);

            // Skip empty answers.
            if ($answertext == '') {
                continue;
            }

            // Standardize vertical align of img tags.
            $answertext = preg_replace($imgsearch, $imgreplace, $answertext);

            // Prepare the $answer object.
            $answer = (object) [
                'question' => $question->id,
                'fraction' => ($i + 1), // Start at 1.
                'answer' => $answertext,
                'answerformat' => $answerformat,
                'feedback' => '',
                'feedbackformat' => FORMAT_MOODLE,
            ];

            // Add/insert $answer into the database.
            if ($answer->id = array_shift($answerids)) {
                if (!$DB->update_record('question_answers', $answer)) {
                    $result->error = get_string('cannotupdaterecord', 'error', 'question_answers (id='.$answer->id.')');
                    return $result;
                }
            } else {
                unset($answer->id);
                if (!$answer->id = $DB->insert_record('question_answers', $answer)) {
                    $result->error = get_string('cannotinsertrecord', 'error', 'question_answers');
                    return $result;
                }
            }

            // Copy files across from draft files area.
            // Note: we must do this AFTER inserting the answer record
            // because the answer id is used as the file's "itemid".
            if ($answeritemid) {
                $answertext = file_save_draft_area_files($answeritemid, $context->id, 'question', 'answer', $answer->id,
                    $this->fileoptions, $answertext);
                $DB->set_field('question_answers', 'answer', $answertext, ['id' => $answer->id]);
            }
        }
        // Create $options for this ddingroups question.
        $options = (object) [
            'questionid' => $question->id,
            'gradingtype' => $question->gradingtype,
            'showgrading' => $question->showgrading,
        ];
        $options = $this->save_combined_feedback_helper($options, $question, $context, true);
        $this->save_hints($question, true);

        // Add/update $options for this ddingroups question.
        if ($options->id = $DB->get_field('qtype_ddingroups_options', 'id', ['questionid' => $question->id])) {
            if (!$DB->update_record('qtype_ddingroups_options', $options)) {
                $result->error = get_string('cannotupdaterecord', 'error', 'qtype_ddingroups_options (id='.$options->id.')');
                return $result;
            }
        } else {
            unset($options->id);
            if (!$options->id = $DB->insert_record('qtype_ddingroups_options', $options)) {
                $result->error = get_string('cannotinsertrecord', 'error', 'qtype_ddingroups_options');
                return $result;
            }
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
        // Load the answers - "fraction" is used to signify the order of the answers,
        // with id as a tie-break which should not be required.
        if (!$question->options->answers = $DB->get_records('question_answers',
                ['question' => $question->id], 'fraction, id')) {
            echo $OUTPUT->notification('Error: Missing question answers for ddingroups question ' . $question->id . '!');
            return false;
        }

        parent::get_question_options($question);
        return true;
    }

    public function delete_question($questionid, $contextid): void {
        global $DB;
        $DB->delete_records('qtype_ddingroups_options', ['questionid' => $questionid]);
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
        $gradingtype = '(?:ABSOLUTE_POSITION|'.
            'ABSOLUTE|ABS|'.
            'RELATIVE_TO_CORRECT|'.
            'RELATIVE|REL)?';
        $showgrading = '(?:SHOW|TRUE|YES|1|HIDE|FALSE|NO|0)?';
        $search = 
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
        $gradingtype = trim($matches[1]);
        $showgrading = trim($matches[2]);

        $answers = preg_split('/[\r\n]+/', $matches[7]);
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

        
        $this->set_options_for_import($question,  $gradingtype
            $showgrading);

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
     * Given question object, returns array with array layouttype, selecttype, selectcount, gradingtype, showgrading
     * where layouttype, selecttype, gradingtype and showgrading are string representations.
     *
     * @param stdClass $question
     * @return array(layouttype, selecttype, selectcount, gradingtype, $showgrading, $numberingstyle)
     */
    public function extract_options_for_export(stdClass $question): array {
        $gradingtype = match ($question->options->gradingtype) {
            qtype_ordering_question::GRADING_ABSOLUTE_POSITION => 'ABSOLUTE_POSITION',
            qtype_ordering_question::GRADING_RELATIVE_TO_CORRECT => 'RELATIVE_TO_CORRECT',
            default => '', // Shouldn't happen !!
        };
        $showgrading = match ($question->options->showgrading) {
            0 => 'HIDE',
            1 => 'SHOW',
            default => '', // Shouldn't happen !!
        };
        return [ $gradingtype, $showgrading];
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

        list($gradingtype,$showgrading) =
            $this->extract_options_for_export($question);
        $output .= ">$gradingtype $showgrading ".PHP_EOL;

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

        // Extra fields - "selecttype" and "selectcount"
        // (these fields used to be called "logical" and "studentsee").
        $gradingtype = $format->getpath($data, ['#', 'gradingtype', 0, '#'], 'RELATIVE');
        $showgrading = $format->getpath($data, ['#', 'showgrading', 0, '#'], '1');
        $this->set_options_for_import($newquestion,
             $showgrading);

        $newquestion->answer = [];
        $newquestion->answerformat = [];
        $newquestion->fraction = [];
        $newquestion->feedback = [];
        $newquestion->feedbackformat = [];

        $i = 0;
        while ($answer = $format->getpath($data, ['#', 'answer', $i], '')) {
            $ans = $format->import_answer($answer, true, $format->get_format($newquestion->questiontextformat));
            $newquestion->answer[$i] = $ans->answer;
            $newquestion->fraction[$i] = 1; // Will be reset later in save_question_options().
            $newquestion->feedback[$i] = $ans->feedback;
            $i++;
        }

        $format->import_combined_feedback($newquestion, $data);
        $newquestion->shownumcorrect = $format->getpath($data, ['#', 'shownumcorrect', 0, '#'], null);

       

        if (!isset($newquestion->shownumcorrect)) {
            $newquestion->shownumcorrect = 1;
        }

        return $newquestion;
    }

    /**
     * Set layouttype, selecttype, selectcount, gradingtype, showgrading based on their textual representation
     *
     * @param stdClass $question the question object
     * @param string $showgrading the grading details or not
     */
    public function set_options_for_import(stdClass $question, 
           string $showgrading, string $gradingtype): void {
            $question->gradingtype = match (strtoupper($gradingtype)) {
                'ABS', 'ABSOLUTE', 'ABSOLUTE_POSITION' => qtype_ordering_question::GRADING_ABSOLUTE_POSITION,
                'RELATIVE_TO_CORRECT' => qtype_ordering_question::GRADING_RELATIVE_TO_CORRECT,
                default => qtype_ordering_question::GRADING_RELATIVE_NEXT_EXCLUDE_LAST,
            };
        // Set "showgrading" option.
        $question->showgrading = match (strtoupper($showgrading)) {
            'HIDE', 'FALSE', 'NO' => 0,
            default => 1,
        };

        
    }
}