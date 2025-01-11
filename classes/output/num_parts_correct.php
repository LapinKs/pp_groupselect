<?php
/**
 *
 * @package   qtype_ddingroups
 * @copyright -
 * @author    Konstantin Lapin <kostyalapin777@mail.ru>
 */
namespace qtype_ddingroups\output;

class num_parts_correct extends renderable_base {
    /**
     * Экспорт данных для шаблона.
     *
     * @param \renderer_base $output Рендерер шаблонов.
     * @return array Массив данных для шаблона.
     */
    public function export_for_template(\renderer_base $output): array {
        // Получаем количество правильных, частично правильных и неправильных элементов.
        list($numright, $numpartial, $numincorrect) = $this->qa->get_question()->get_num_parts_right(
            $this->qa->get_last_qt_data()
        );

        // Добавляем информацию о распределении по группам (если это необходимо для визуализации).
        $groupsummary = $this->qa->get_question()->get_group_summary($this->qa->get_last_qt_data());

        return [
            'numcorrect' => $numright, // Количество правильных ответов.
            'numpartial' => $numpartial, // Количество частично правильных ответов.
            'numincorrect' => $numincorrect, // Количество неправильных ответов.
            'groupsummary' => $groupsummary, // Сводка по группам (опционально, если используется).
        ];
    }
}
