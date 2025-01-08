<?php

namespace qtype_ddingroups\output;


class num_parts_correct extends renderable_base {
    public function export_for_template(\renderer_base $output): array {

        list($numright, $numpartial, $numincorrect) = $this->qa->get_question()->get_num_parts_right(
            $this->qa->get_last_qt_data());

        return [
                'numcorrect' => $numright,
                'numpartial' => $numpartial,
                'numincorrect' => $numincorrect,
        ];
    }
}
