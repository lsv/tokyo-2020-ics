<?php

namespace Tokyo2020Ics;

use DateTime;
use DateTimeZone;
use Eluceo\iCal\Component\Calendar;
use Eluceo\iCal\Component\Event;
use InvalidArgumentException;
use stdClass;
use Symfony\Component\DomCrawler\Crawler;

class Generate
{

    private const URL = 'https://www2.tokyo2020.org';

    public static function ics(): string
    {
        $calendar = new Calendar('lsv/tokyo2020-ics');
        self::getEventPage($calendar);

        return $calendar->render();
    }

    private static function getEventPage(Calendar $calendar): void
    {
        $crawler = new Crawler(file_get_contents(self::URL.'/en/games/schedule/olympic/'));
        $filtered = $crawler->filter('.pd_view .schTable td a');
        $count = $filtered->count();
        $current = 0;
        $filtered->each(
            static function (Crawler $node) use ($calendar, $count, &$current) {
                $current++;
                echo "{$current} / {$count}\n";

                $url = $node->attr('href');
                self::parseEventPage($calendar, $url);
            }
        );
    }

    private static function parseEventPage(Calendar $calendar, string $url): void
    {
        $crawler = new Crawler(file_get_contents(self::URL.'/'.$url));

        $summary = trim($crawler->filter('.contentRightSidebar h2 span')->text());

        $crawler->filter('.contentRightSidebar .schDetailItem')->each(
            static function (Crawler $eventNode) use ($calendar, $url, $summary) {
                $event = new Event();
                $event->setUrl(self::URL.'/'.$url);
                $event->setSummary($summary);

                $date = self::getDate($eventNode);
                $event->setDtStart($date->start);
                if ($date->end) {
                    $event->setDtEnd($date->end);
                }

                $event->setDescriptionHTML(self::getDescription($eventNode));
                $event->setLocation(self::getLocation($eventNode));

                $calendar->addComponent($event);
            }
        );
    }

    private static function getLocation(Crawler $eventNode): string
    {
        return trim(str_replace('Venues:', '', $eventNode->filter('.schDetailPlace')->text()));
    }

    private static function getDescription(Crawler $eventNode): string
    {
        return '<ul>' . $eventNode->filter('.schDetailContents')->html() . '</ul>';
    }

    private static function getDate(Crawler $eventNode): stdClass
    {
        $buildDate = static function (string $month, int $day, ?int $hour, ?int $min): DateTime {
            return (new DateTime("{$day} {$month} 2020"))
                ->setTimezone(new DateTimeZone('Asia/Tokyo'))
                ->setTime($hour ?: 8, $min ?: 0);
        };

        $date = $eventNode->filter('.schDetailTime')->text();
        $date = trim(str_replace('Date and Time:', '', $date));

        $startTime = $date;
        $endTime = null;
        if (strpos($date, ' - ') !== false) {
            [$startTime, $endTime] = explode(' - ', $date);
        }

        preg_match('/(\w+) (\d{1,2}) (\w+)( \d{1,2}:\d{2})?/', $startTime, $matches);
        if (isset($matches[0])) {
            [, , $day, $month] = $matches;
            $hour = null;
            $min = null;
            if (isset($matches[4])) {
                [$hour, $min] = explode(':', $matches[4]);
            }

            $startDate = $buildDate($month, (int)$day, $hour, $min);
            $endDate = null;
            if ($endTime) {
                $endDate = clone $startDate;
                [$hour, $min] = explode(':', $endTime);
                $endDate->setTime($hour, $min);
            }

            $obj = new stdClass();
            $obj->start = $startDate;
            $obj->end = $endDate;

            return $obj;
        }

        throw new InvalidArgumentException('Could not parse date "'.$date.'"');
    }

}
