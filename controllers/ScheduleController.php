<?php

class ScheduleController extends Controller
{
    public function filters()
    {
        return [
            'accessControl',
        ];
    }

    public function accessRules()
    {
        return [
            ['allow', 'users' => ['*']],
        ];
    }

    public function actionIndex($group = false)
    {
        /** @var Group $group */
        if ($group != false && !($group = Group::model()->filled()->findByAttributes(['number' => $group])))
            throw new CHttpException(404, 'Данной группы не найден');
        /** @var Semesters $semester */
        $semester = Semesters::model()->actual();
        $current_date = date('Y-m-d');
        $current_time = strtotime($current_date);
        if (!$semester)
            throw new CHttpException(404, 'Сейчас нет семестра :-(');
        $group_list = CHtml::listData(Group::model()->filled()->findAll(), 'number', 'number');

        $schedule = [];
        if ($group) {
            $time_one_day = 60 * 60 * 24;
            for ($i = $current_time; $i <= $current_time + $time_one_day * 7; $i = $i + $time_one_day) {
                $week_day = date('N', $i);
                $date = date('d.n.Y', $i);
                /** @var Holiday $holiday */
                if (($holiday = Holiday::model()->findByAttributes(['date' => date('Y-m-d', $i)])) || $week_day == 7) {
                    $schedule[$date] = ['holiday' => true];
                    if ($holiday)
                        $schedule[$date]['name'] = $holiday->name;
                    continue;
                }
                $schedule[$date] = [];
                $week_number = (($semester->week_number + (date('W', $i) - date('W', strtotime($semester->start_date)))) % 2) ? 1 : 2;
                $schedule_elements = ScheduleElement::model()->findAllByAttributes(['group_id' => $group->id, 'semester_id' => $semester->id, 'week_number' => $week_number, 'week_day' => $week_day]);

                $numbers = [1, 2, 3, 4, 5];
                /** @var ScheduleElement $schedule_element */
                foreach ($schedule_elements as $schedule_element) {
                    unset($numbers[array_search($schedule_element->number, $numbers)]);
                    /** @var GroupReplace $replace */
                    $replace = GroupReplace::model()->findByAttributes(['date' => date('Y-m-d', $i), 'number' => $schedule_element->number, 'group_id' => $group->id]);
                    if ($replace && $replace->cancel) {
                        $schedule[$date][$schedule_element->number] = [
                            'cancel' => true,
                            'replace' => true
                        ];
                    } elseif ($replace) {
                        $schedule[$date][$schedule_element->number] = [
                            'subject' => $replace->subject->name,
                            'replace' => true,
                            'comment' => $replace->comment
                        ];
                        if ($replace->teacher_id)
                            $schedule[$date][$schedule_element->number]['teacher'] = $replace->teacher->lastname . ' ' . mb_substr($replace->teacher->firstname, 0, 1, "UTF-8") . '.' . mb_substr($replace->teacher->middlename, 0, 1, "UTF-8") . '.';
                        if ($replace->classroom_id)
                            $schedule[$date][$schedule_element->number]['classroom'] = $replace->classroom->name;
                    } else {
                        $schedule[$date][$schedule_element->number] = [
                            'subject' => $schedule_element->subject->name
                        ];
                        if ($schedule_element->teacher_id)
                            $schedule[$date][$schedule_element->number]['teacher'] = $schedule_element->teacher->lastname . ' ' . mb_substr($schedule_element->teacher->firstname, 0, 1, "UTF-8") . '.' . mb_substr($schedule_element->teacher->middlename, 0, 1, "UTF-8") . '.';
                        if ($schedule_element->classroom_id)
                            $schedule[$date][$schedule_element->number]['classroom'] = $schedule_element->classroom->name;
                    }
                }
                if (count($numbers))
                    foreach ($numbers as $number) {
                        /** @var GroupReplace $replace */
                        $replace = GroupReplace::model()->findByAttributes(['date' => date('Y-m-d', $i), 'number' => $number, 'group_id' => $group->id]);
                        if ($replace && $replace->cancel) {
                            $schedule[$date][$replace->number] = [
                                'cancel' => true,
                                'replace' => true
                            ];
                        } elseif ($replace) {
                            $schedule[$date][$replace->number] = [
                                'subject' => $replace->subject->name,
                                'replace' => true
                            ];
                            if ($replace->teacher_id)
                                $schedule[$date][$replace->number]['teacher'] = $replace->teacher->lastname . ' ' . mb_substr($replace->teacher->firstname, 0, 1, "UTF-8") . '.' . mb_substr($replace->teacher->middlename, 0, 1, "UTF-8") . '.';
                            if ($replace->classroom_id)
                                $schedule[$date][$replace->number]['classroom'] = $replace->classroom->name;
                        }
                    }
                ksort($schedule[$date]);
            }
        }
        Yii::app()->clientScript->registerMetaTag('noarchive', 'robots');
        $this->render('index', ['group' => ($group ? $group->number : false), 'group_list' => $group_list, 'schedule' => $schedule]);
    }
}