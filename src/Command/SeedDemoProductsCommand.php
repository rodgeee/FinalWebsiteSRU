<?php

namespace App\Command;

use App\Entity\Products;
use App\Entity\Stocks;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-demo-products',
    description: 'Insert sample products when the catalog is empty (e.g. first Railway deploy)',
)]
final class SeedDemoProductsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $existing = (int) $this->entityManager->getRepository(Products::class)->count([]);

        if ($existing > 0) {
            $io->writeln(sprintf('Catalog already has %d product(s); nothing to seed.', $existing));

            return Command::SUCCESS;
        }

        $now = new \DateTimeImmutable();
        $demos = [
            ['name' => 'Adidas Adizero Evo SL W', 'color' => 'Cloud White', 'size' => '8', 'price' => '8999.00', 'image' => 'img/Adi zero white.png'],
            ['name' => 'ASICS GEL-Kayano 20', 'color' => 'Friends and Family', 'size' => '9', 'price' => '10999.00', 'image' => 'img/ASICS GEL KAYANO 20 FRIENDS AND FAMILY.png'],
            ['name' => 'New Balance 1906R', 'color' => 'Moon Sign', 'size' => '10', 'price' => '9499.00', 'image' => 'img/1906r moon sign.png'],
            ['name' => 'Nike Air Jordan 1', 'color' => 'Lost and Found', 'size' => '9', 'price' => '12999.00', 'image' => 'img/J1 LOST AND FOUND.png'],
            ['name' => 'Puma RS-X', 'color' => 'Classic', 'size' => '8', 'price' => '5999.00', 'image' => 'img/PUMA-01.png'],
        ];

        foreach ($demos as $row) {
            $product = (new Products())
                ->setName($row['name'])
                ->setColor($row['color'])
                ->setSize($row['size'])
                ->setPrice($row['price'])
                ->setDescription('Demo listing for storefront preview.')
                ->setImage($row['image'])
                ->setImages([$row['image']]);

            $stock = (new Stocks())
                ->setProduct($product)
                ->setQuantity(10)
                ->setCreatedAt($now)
                ->setUpdatedAt($now);

            $product->addStock($stock);
            $this->entityManager->persist($product);
        }

        $this->entityManager->flush();

        $io->success(sprintf('Created %d demo products.', count($demos)));

        return Command::SUCCESS;
    }
}
