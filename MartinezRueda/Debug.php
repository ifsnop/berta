<?php
namespace MartinezRueda;

/**
 * Class Debug
 * @package MartinezRueda
 */
class Debug
{
    public static $debug_on = true;

    public static function debug(/*callable */$callee)
    {
        if (self::$debug_on) {
            $callee();
        }
    }

    /**
     * @param SweepEvent $event
     * @return string
     */
    public static function gatherSweepEventData(SweepEvent $event)/* : string*/
    {
        $data = array(
            'index' => $event->id,
            'is_left' => $event->is_left ? 1 : 0,
            'x' => $event->p->x,
            'y' => $event->p->y,
            'other' => array('x' => $event->other->p->x, 'y' => $event->other->p->y)
        );

        return json_encode($data);
    }

    /**
     * @param Connector $connector
     * @return string
     */
    public static function gatherConnectorData(Connector $connector)/* : string*/
    {
        $open_polygons = array();
        $closed_polygons = array();

        foreach ($connector->open_polygons as $chain) {
            $open_polygons[] = self::gatherPointChainData($chain);
        }

        foreach ($connector->closed_polygons as $chain) {
            $closed_polygons[] = self::gatherPointChainData($chain);
        }

        $data = array(
            'closed' => $connector->isClosed() ? 1 : 0,
            'open_polygons' => $open_polygons,
            'closed_polygons' => $closed_polygons
        );

        return json_encode($data);
    }

    /**
     * @param PointChain $chain
     * @return array
     */
    protected function gatherPointChainData(PointChain $chain)/* : array*/
    {
        $points = array();

        if (!empty($chain->segments)) {
            foreach ($chain->segments as $point) {
                $points[] = array('x' => $point->x, 'y' => $point->y);
            }
        }

        $data = array(
            'closed' => $chain->closed ? 1 : 0,
            'elements' => $points
        );

        return $data;
    }
}