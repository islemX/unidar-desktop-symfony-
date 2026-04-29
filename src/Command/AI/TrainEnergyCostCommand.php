<?php

namespace App\Command\AI;

use App\Ml\Model\EnergyCostModel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'ai:train:energy-cost', description: 'Train the energy cost estimation model')]
class TrainEnergyCostCommand extends Command
{
    public function __construct(
        private readonly EnergyCostModel $model,
    ) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Training Energy Cost Estimation Model');

        [$samples, $costs] = $this->generateSyntheticData();
        $io->writeln(sprintf('Synthetic samples: %d', count($samples)));

        $result = $this->model->train($samples, $costs);
        $io->success(sprintf('Trained on %d samples', $result['samples']));

        return Command::SUCCESS;
    }

    /**
     * Generates realistic Tunisian apartment energy cost samples.
     *
     * Feature vector (matches EnergyCostModel::extract()):
     *   [area, bedrooms, floor, has_ac, has_washer, has_solar, has_electric_heating]
     *
     * Label: estimated monthly energy cost in TND
     * (electricity ≈ base 25 TND + ~0.35/m² + appliance loads, water ≈ base 8 TND)
     */
    private function generateSyntheticData(): array
    {
        $samples = [];
        $costs   = [];

        // ── Small studios / kots ─────────────────────────────────────
        for ($i = 0; $i < 100; $i++) {
            $area     = rand(18, 40);
            $hasAc    = (int)(rand(0, 10) < 3);
            $hasSolar = (int)(rand(0, 10) < 2);
            $hasElec  = (int)(rand(0, 10) < 2);

            $cost = 25
                + $area * 0.32
                + $hasAc    * rand(20, 35)
                + $hasSolar * (-8)
                + $hasElec  * rand(15, 25)
                + rand(-5, 5);                // noise

            $samples[] = [
                (float) $area,
                1.0,                          // bedrooms
                (float) rand(0, 5),           // floor
                (float) $hasAc,
                (float)(rand(0, 10) < 4),     // washer
                (float) $hasSolar,
                (float) $hasElec,
            ];
            $costs[] = max(20.0, (float) $cost);
        }

        // ── Mid-size apartments (2–3 bedrooms) ──────────────────────
        for ($i = 0; $i < 150; $i++) {
            $area     = rand(50, 110);
            $beds     = rand(2, 3);
            $hasAc    = (int)(rand(0, 10) < 5);
            $hasWash  = (int)(rand(0, 10) < 7);
            $hasSolar = (int)(rand(0, 10) < 3);
            $hasElec  = (int)(rand(0, 10) < 4);

            $cost = 35
                + $area * 0.38
                + $hasAc    * rand(25, 45)
                + $hasWash  * rand(8, 14)
                + $hasSolar * (-12)
                + $hasElec  * rand(20, 35)
                + rand(-8, 8);

            $samples[] = [
                (float) $area,
                (float) $beds,
                (float) rand(0, 8),
                (float) $hasAc,
                (float) $hasWash,
                (float) $hasSolar,
                (float) $hasElec,
            ];
            $costs[] = max(30.0, (float) $cost);
        }

        // ── Large / shared housing ───────────────────────────────────
        for ($i = 0; $i < 100; $i++) {
            $area     = rand(100, 200);
            $beds     = rand(3, 6);
            $hasAc    = (int)(rand(0, 10) < 7);
            $hasWash  = 1;
            $hasSolar = (int)(rand(0, 10) < 4);
            $hasElec  = (int)(rand(0, 10) < 5);

            $cost = 55
                + $area * 0.42
                + $hasAc    * rand(35, 60)
                + $hasWash  * rand(10, 16)
                + $hasSolar * (-18)
                + $hasElec  * rand(25, 45)
                + rand(-10, 10);

            $samples[] = [
                (float) $area,
                (float) $beds,
                (float) rand(0, 12),
                (float) $hasAc,
                (float) $hasWash,
                (float) $hasSolar,
                (float) $hasElec,
            ];
            $costs[] = max(50.0, (float) $cost);
        }

        return [$samples, $costs];
    }
}
