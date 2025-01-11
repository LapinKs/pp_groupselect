<?php
/**
 *
 * @package   qtype_ddingroups
 * @copyright -
 * @author    Konstantin Lapin <kostyalapin777@mail.ru>
 */
namespace qtype_ddingroups\output;

use templatable;
use renderable;
use question_attempt;


abstract class renderable_base implements templatable, renderable {

    /**
     * The class constructor.
     *
     * @param question_attempt $qa The question attempt object.
     */
    public function __construct(
        /** @var question_attempt The question attempt object. */
        protected question_attempt $qa,
    ) {
    }
}
