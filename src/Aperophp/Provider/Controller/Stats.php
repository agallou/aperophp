<?php

namespace Aperophp\Provider\Controller;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Aperophp\Repository\City;

class Stats implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];

        $controllers->match('stats_{type}-{city}.html', function(Request $request, $type, $city) use ($app)
        {
          $app['session']->set('menu', 'stats');
          $types = array(
            'all' => 'Toutes',
            'year' => 'Depuis un an',
            'month' => 'Depuis 6 mois',
          );

          if (!isset($types[$type])) {
            $type = 'all';
          }
          $dateFrom = null;
          $date = new \DateTime();
          if ($type == 'year') {
            $date->modify('-1 year');
            $dateFrom = $date->format('Y-m-d');
          } elseif ($type == 'month') {
            $date->modify('-6 month');
            $dateFrom = $date->format('Y-m-d');
          }

          $totalCount = $app['drinks']->getCount($dateFrom, $city);

          $geo = array();
          foreach ($app['drinks']->getGeoInformations($dateFrom) as $info) {
            $geo[] = array($info['latitude'], $info['longitude'], $info['description']);
          }

          $displayedDate = $app['drinks']->findFirst($dateFrom);
          if (count($displayedDate)) {
            $displayedDate = $dateFrom;
          }

          $cities = array(City::ALL => 'Toutes') + $app['cities']->findRecurrentInAssociativeArray();

          return $app['twig']->render('stats/stats.html.twig', array(
            'total' => $totalCount,
            'total_participants' => $app['drinks']->countAllParticipants($dateFrom, $city),
            'avg_participants' => $app['drinks']->averageParticipantsByCity($dateFrom, 'all' == $type),
            'date_participants' => $app['drinks']->countParticipantsByDate($dateFrom, $city),
            'date_from' => $displayedDate,
            'geo' => $geo,
            'type' => $type,
            'types' => $types,
            'city' => $city,
            'cities' => $cities,
            'display_all_cities' => $city == City::ALL,
            ));
        })
        ->bind('_stats')
        ->method('GET');

        return $controllers;
    }
}
