<?php

namespace Psalm\Internal\SourceControl;

/**
 * @author Kitamura Satoshi <with.no.parachute@gmail.com>
 * @author Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * @internal
 */
final class SystemCommandExecutor
{
    /**
     * Execute command.
     *
     * @param string $command
     *
     * @throws \RuntimeException
     *
     * @return string[]
     */
    public function execute(string $command) : array
    {
        exec($command, $result, $returnValue);

        if ($returnValue === 0) {
            /** @var string[] */
            return $result;
        }

        throw new \RuntimeException(sprintf('Failed to execute command: %s', $command), $returnValue);
    }
}
