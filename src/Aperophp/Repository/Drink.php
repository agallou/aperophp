<?php

namespace Aperophp\Repository;

use Aperophp\Repository\City;


/**
 * Drink repository
 */
class Drink extends Repository
{
    const KIND_DRINK        = 'drink';
    const KIND_CONFERENCE   = 'talk';

    public function getTableName()
    {
        return 'Drink';
    }

    protected function getBaseDrinkQuery($dateFrom = null, $city = City::ALL)
    {
      $queryBuilder = $this->db->createQueryBuilder()
        ->from('Drink', 'd')
      ;
      if (null !== $dateFrom) {
        $queryBuilder->andWhere('day > :datefrom');
        $queryBuilder->setParameter('datefrom', $dateFrom);
      }
      if ($city != City::ALL) {
       $queryBuilder->andWhere(sprintf('city_id = %s', $city));
      }

      return $queryBuilder;
    }

    public function getCount($dateFrom, $city = City::ALL)
    {
        $queryBuilder = $this->getBaseDrinkQuery($dateFrom, $city)
          ->select('count(d.id) as count')
        ;

        return $queryBuilder->execute()->fetchColumn();
    }


    /**
     * Find drinks order by day with participants
     *
     * @param integer $limit
     *
     * @return array
     */
    public function findAll($limit = null)
    {
        if (null === $limit) {
            $limit = 3;
        }

        $sql  = sprintf(
            'SELECT d.*, m.username as organizer_username, u.email as organizer_email, c.name as city_name,
                (%s) as participants_count
            FROM Drink d, Member m, User u, City c
            WHERE d.member_id = m.id
              AND u.member_id = m.id
              AND d.city_id = c.id
            ORDER BY day DESC
            LIMIT %d
        ', self::getCountParticipantsQuery(), $limit);

        return $this->db->fetchAll($sql);
    }

    public function averageParticipantsByCity($dateFrom = null, $onlyRecurrentCities = false)
    {
      $queryBuilder = $this->getBaseDrinkQuery($dateFrom)
        ->addSelect(sprintf("CEILING(AVG((%s))) as participants_avg", self::getCountParticipantsQuery()))
        ->addSelect('COUNT(d.id) as total_drinks')
        ->addSelect('c.name as name')
        ->innerJoin('d', 'City', 'c', 'd.city_id = c.id')
        ->addGroupBy('c.id')
        ->addOrderBy('participants_avg', 'DESC')
        ->addOrderBy('name')
      ;

      if ($onlyRecurrentCities) {
        $queryBuilder->andHaving('total_drinks > 4');
      }

      return $queryBuilder->execute()->fetchAll();
    }

    public function countAllParticipants($dateFrom = null, $city = City::ALL)
    {
      $queryBuilder = $this->getBaseDrinkQuery($dateFrom, $city)
        ->addSelect('count(*) as count')
        ->innerJoin('d', 'Drink_Participation', 'dp', 'dp.drink_id = d.id')
        ->andWhere('dp.percentage > 0')
      ;

      return $queryBuilder->execute()->fetchColumn();
    }

    public function countParticipantsByDate($dateFrom = null, $city = City::ALL)
    {
      $queryBuilder = $this->getBaseDrinkQuery($dateFrom, $city)
        ->addSelect('count(*) as count')
        ->addSelect('d.day as day')
        ->innerJoin('d', 'Drink_Participation', 'dp', 'dp.drink_id = d.id')
        ->andWhere('dp.percentage > 0')
        ->addGroupBy('day')
      ;
      foreach ($queryBuilder->execute() as $row) {
        $dates[$row['day']] = $row['count'];
      }

      return $dates;
    }

    public function getGeoInformations($dateFrom = null)
    {
      $queryBuilder = $this->getBaseDrinkQuery($dateFrom)
        ->addSelect('latitude', 'longitude', 'description')
        ->addGroupBy('d.id')
        ->addOrderBy('created_at', 'DESC')
      ;
      return $queryBuilder->execute()->fetchAll();
    }


    public function findFirst($dateFrom = null)
    {
      $queryBuilder = $this->getBaseDrinkQuery($dateFrom)
        ->select('*')
        ->addOrderBy('day')
        ->addOrderBy('hour')
        ->addOrderBy('created_at')
        ->setMaxResults(1)
      ;

      return $queryBuilder->execute()->fetch();
    }

    /**
     * Find futur drinks order by day, with participants
     */
    public function findNext($limit = null)
    {
        if (null === $limit) {
            $limit = 3;
        }

        $today = new \DateTime();

        $sql  = sprintf(
            'SELECT d.*, m.username as organizer_username, u.email as organizer_email, c.name as city_name,
                (%s) as participants_count
            FROM Drink d, Member m, User u, City c
            WHERE d.member_id = m.id
              AND u.member_id = m.id
              AND d.city_id = c.id
              AND d.day >= "%s"
              ORDER BY day ASC
            LIMIT %s
        ',
        self::getCountParticipantsQuery(),
        $today->format('Y-m-d') ,
        $limit);

        return $this->db->fetchAll($sql);
    }

    /**
     * Load a specific drink
     *
     * @param integer $id
     * @return array
     */
    public function find($id)
    {
        $sql  =
            sprintf('SELECT d.*, m.username as organizer_username, u.email as organizer_email, c.name as city_name,
                (%s) as participants_count
            FROM Drink d, Member m, User u, City c
            WHERE d.member_id = m.id
              AND u.member_id = m.id
              AND d.city_id = c.id
              AND d.id = ?
            LIMIT 1
            ', self::getCountParticipantsQuery());

        return $this->db->fetchAssoc($sql, array((int) $id));
    }

    public function findAllKindsInAssociativeArray()
    {
        return array(
            self::KIND_DRINK      => self::KIND_DRINK,
            self::KIND_CONFERENCE => self::KIND_CONFERENCE,
        );
    }

    protected static function getCountParticipantsQuery()
    {
      return "SELECT COUNT(*) FROM Drink_Participation WHERE drink_id = d.id AND percentage > 0";
    }

}
