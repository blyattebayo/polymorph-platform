<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Console;

use Polymorph\Platform\Domain\AccessControl\Core\Contracts\PolicyCompilationService;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Subject;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class RebuildAccessControlCommand extends Command
{
    protected $signature = 'access-control:rebuild {--subject= : Rebuild a single subject, for example user:42 or role:editor}';

    protected $description = 'Rebuild compiled access-control policies';

    public function handle(PolicyCompilationService $compiler): int
    {
        $subject = $this->option('subject');

        try {
            if (is_string($subject) && trim($subject) !== '') {
                $compiler->recompileSubject(Subject::fromString($subject));
                $this->info('Rebuilt compiled policies for ' . $subject . '.');

                return self::SUCCESS;
            }

            $count = $compiler->recompileAll();
            $this->info(sprintf('Rebuilt compiled policies for %d subject(s).', $count));

            return self::SUCCESS;
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
