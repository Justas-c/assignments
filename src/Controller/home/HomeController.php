<?php

namespace App\Controller\home;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * HomeController
 */
class HomeController //extends AnotherClass
{
    private $cats_arr = [];

    /**
     * @Route("/{slug}", name="homepage", defaults={"slug"=1})
     */
    public function homepage($slug, CacheInterface $cache, EntityManagerInterface $entityManager)
    {
        // check if url within the parameters
        if ($slug < 1 ||  $slug > 1000000) {
            die('url variable has to be a number from 1 to 1000000. Try again ');
        }

        // get 3 cats and cached it
        $cached_cats = $cache->get($slug, function (ItemInterface $item) {
            $item->expiresAfter(60);
            //echo 'function called on cache miss!';
            return $this->get3Cats();
        });

        // connection
        $conn = $entityManager->getConnection();

        // sql queries, prepare, execute
        $sql = "INSERT INTO cats.stats (name, count) VALUES (:name, :count)
            ON DUPLICATE KEY UPDATE count=count+1";
        $sql2 = "UPDATE cats.stats SET count = count+ 1 WHERE name = 'countAll'";
        $stmt = $conn->prepare($sql);
        $stmt2 = $conn->prepare($sql2);
        $stmt->execute([':name' => $slug, ':count' => 1]);
        $stmt2->execute();

        // for json.txt file:
        $request = Request::createFromGlobals();
        $time = $request->server->get('REQUEST_TIME');
        $cats = $cached_cats;

        // sql queries, prepare, execute, fetch
        $sql3 = "SELECT * FROM cats.stats WHERE name IN (:countAll, :num)";
        $stmt3 = $conn->prepare($sql3);
        $stmt3->execute([':countAll' => 'countAll',':num' => $slug]);
        $row = $stmt3->fetchAll();

        // results
        $countN = $row[0]['count'];
        $countAll = $row[1]['count'];

        $data = ['datetime' => date("Y-m-d H:i:s", $time), "N" => $slug, 'cats' => $cats, 'countAll' => $countAll, 'countN' => $countN];
        $json_encoded = json_encode($data);

        // write data
        file_put_contents('json_log.txt', $json_encoded . PHP_EOL, FILE_APPEND);

        //Response
        return new Response(implode(', ' , $cached_cats));
    }

    // get 3 cats
    private function get3Cats()
    {
        $all_cats = file('cats.txt', FILE_IGNORE_NEW_LINES);
        $random_cats = [];

        // get 3 random cats
        for ($i=0; $i < 3; $i++) {
            $random_cats[] = $all_cats[(rand(0, count($all_cats) -1))];
        }
        return $random_cats;
    }

}
