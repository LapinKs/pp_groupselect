<?php
/**
 * Version information for the mulridrop question type.
 *
 * @package    qtype
 * @subpackage ddingroups 
 */

class qtype_ddingroups_question extends question_graded_automatically {
    // Основные настройки.
    public $groupcount;
    public $sequencecheck;
    public $correctfeedback;
    public $correctfeedbackformat;
    public $incorrectfeedback;
    public $incorrectfeedbackformat;
    public $shownumcorrect;

    // Элементы для перетаскивания.
    public $items = [];
    public $groupid;
    public $sortorder;
    public $itemsequencecheck;

    const GRADING_ABSOLUTE_POSITION = 0;

    const GRADING_RELATIVE_POSITION = 1;
    // Ответы.
    public $correctresponse = [];
    public $currentresponse = [];
    protected $itemscores = [];

    class qtype_ddingroups extends question_graded_automatic {

        /**
         * Метод для проверки ошибки валидации.
         * Возвращает строку с описанием ошибки, если есть.
         *
         * @param array $response Ответ пользователя.
         * @return string Описание ошибки или пустая строка.
         */
        public function get_validation_error(array $response) {
            
            return '';
        }
    
        /**
         * Метод оценки ответа.
         * Возвращает дробное значение (fraction) и статус оценки.
         *
         * @param array $response Ответ пользователя.
         * @return array [fraction, state].
         */
        public function grade_response(array $response) {
            $this->update_current_response($response);
    
            $countcorrect = 0;
            $countanswers = 0;
    
           
            foreach ($this->correctresponse as $groupid => $correctitems) {
                if (isset($this->currentresponse[$groupid])) {
                    $useritems = $this->currentresponse[$groupid];
                    foreach ($correctitems as $item) {
                        if (in_array($item, $useritems)) {
                            $countcorrect++;
                        }
                    }
                    $countanswers += count($correctitems);
                }
            }
    
            // Рассчитываем дробную оценку.
            $fraction = ($countanswers > 0) ? $countcorrect / $countanswers : 0;
    
            return [
                $fraction,
                question_state::graded_state_for_fraction($fraction),
            ];
        }
    
        /**
         * Получить правильный ответ для сравнения.
         *
         * @return array Массив с правильным распределением элементов.
         */
        public function get_correct_response() {
            $correctresponse = [];
            foreach ($this->correctresponse as $groupid => $items) {
                $correctresponse[$groupid] = array_map(function ($item) {
                    return $this->answers[$item]->md5key;
                }, $items);
            }
            return $correctresponse;
        }
    
        /**
         * Ожидаемые данные из ответа пользователя.
         *
         * @return array
         */
        public function get_expected_data() {
            return ['response' => PARAM_RAW];
        }
    
        /**
         * Проверяет, является ли ответ пользователя полным.
         *
         * @param array $response Ответ пользователя.
         * @return bool
         */
        public function is_complete_response(array $response) {
            
            return !empty($response['response']);
        }
    
        /**
         * Сравнивает два ответа пользователя.
         *
         * @param array $prevresponse Предыдущий ответ.
         * @param array $newresponse Новый ответ.
         * @return bool
         */
        public function is_same_response(array $prevresponse, array $newresponse) {
            return $prevresponse === $newresponse;
        }
    
        /**
         * Возвращает краткое описание ответа пользователя.
         *
         * @param array $response Ответ пользователя.
         * @return string
         */
        public function summarise_response(array $response) {
            if (empty($response['response'])) {
                return '';
            }
    
            $summary = [];
            foreach ($response['response'] as $groupid => $items) {
                $itemtexts = [];
                foreach ($items as $item) {
                    if (isset($this->answers[$item])) {
                        $text = $this->answers[$item]->answer;
                        $text = shorten_text($this->html_to_text($text, $this->answers[$item]->answerformat), 10);
                        $itemtexts[] = $text;
                    }
                }
                $summary[] = "Group $groupid: " . implode(', ', $itemtexts);
            }
            return implode('; ', $summary);
        }
    
        /**
         * Обновляет текущий ответ (помещает данные в $this->currentresponse).
         *
         * @param array $response Ответ пользователя.
         */
        protected function update_current_response(array $response) {
            $this->currentresponse = $response['response'] ?? [];
        }
    }
    
}

    

    

    

    




 


    

