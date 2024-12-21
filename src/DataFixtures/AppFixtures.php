<?php

namespace App\DataFixtures;

use App\Entity\Article;
use App\Entity\Client;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Création des articles
        for ($i = 1; $i <= 10; $i++) {
            $article = new Article();
            $article->setLibelle("Article $i");
            $article->setDescription("Description pour Article $i");
            $article->setPrix(5 + $i); // Prix dynamique
            $article->setQteStock(100 + $i); // Quantité dynamique

            $manager->persist($article);
        }

        // Création des clients
        for ($i = 1; $i <= 10; $i++) {
            $client = new Client();
            $client->setNom("Nom$i");
            $client->setPrenom("Prenom$i");
            $client->setTelephone("62359000$i");
            $client->setVille("Ville $i");
            $client->setQuartier("Quartier $i");
            $client->setNumVilla("Villa $i");

            $manager->persist($client);
        }

        $manager->flush();
    }
}
