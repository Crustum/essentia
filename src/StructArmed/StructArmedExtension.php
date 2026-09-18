<?php
declare(strict_types=1);

namespace Crustum\Essentia\StructArmed;

use Boundwize\StructArmed\Analyser\Analyser;
use Boundwize\StructArmed\Baseline\BaselineFilter;
use Boundwize\StructArmed\Cache\AnalysisCacheMetadataFactory;
use Boundwize\StructArmed\Cache\AnalysisResultCache;
use Boundwize\StructArmed\Cache\FileHashProvider;
use Boundwize\StructArmed\Config\ConfigLoader;
use Boundwize\StructArmed\Progress\ConsoleProgressBar;
use Boundwize\StructArmed\Rule\RuleViolationCollection;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * PHPUnit extension that runs StructArmed inside a test run.
 *
 * Mirrors the upstream StructArmed extension, but instead of echoing a console
 * report and throwing on violations, it stores the collection so the PHPUnit
 * driver can surface each violation in its structured JSON output.
 *
 * @internal
 * @codeCoverageIgnore
 */
final class StructArmedExtension implements Extension
{
    /**
     * Bootstrap StructArmed analysis for the current test run.
     *
     * @param \PHPUnit\TextUI\Configuration\Configuration $configuration
     * @param \PHPUnit\Runner\Extension\Facade $facade
     * @param \PHPUnit\Runner\Extension\ParameterCollection $parameters
     * @return void
     */
    public function bootstrap(
        Configuration $configuration,
        Facade $facade,
        ParameterCollection $parameters,
    ): void {
        $cwd = getcwd();
        $basePath = $cwd !== false ? $cwd : '';

        $configFile = $parameters->has('config')
            ? $parameters->get('config')
            : ConfigLoader::discover($basePath);

        $architecture = ConfigLoader::load($configFile);

        $fileHashProvider = new FileHashProvider();
        $analysisCacheMetadataFactory = new AnalysisCacheMetadataFactory($fileHashProvider);
        $configHash = $analysisCacheMetadataFactory->fileHash($configFile);
        $composerGeneratedVersionHash = $analysisCacheMetadataFactory->composerGeneratedVersionHash();
        $analysisResultCache = new AnalysisResultCache(
            $basePath,
            $fileHashProvider,
            $architecture->getCacheDirectory(),
            $configHash,
            $composerGeneratedVersionHash,
        );

        if ($analysisResultCache->shouldInvalidate()) {
            $analysisResultCache->clear();
        }

        $analyser = new Analyser(
            $basePath,
            $analysisResultCache,
            $analysisCacheMetadataFactory->classNodeCacheNamespace($basePath, $configHash),
        );

        $files = $analyser->filesForAnalysis($architecture);
        $metadata = $analysisCacheMetadataFactory->metadata($basePath, $configFile, [], $files);
        $cacheKey = $analysisCacheMetadataFactory->key($metadata);

        $ruleViolationCollection = $analysisResultCache->load($cacheKey, $metadata);

        if (!$ruleViolationCollection instanceof RuleViolationCollection) {
            $progressHandler = $this->isProgressEnabled($parameters) ? new ConsoleProgressBar() : null;

            $ruleViolationCollection = $analyser->analyse(
                $architecture,
                progressHandler: $progressHandler,
                files: $files,
            );
            $analysisResultCache->store($cacheKey, $metadata, $ruleViolationCollection);
        }

        $ruleViolationCollection = (new BaselineFilter())->apply($ruleViolationCollection, $architecture, $basePath);

        StructArmedCollector::set($ruleViolationCollection);
    }

    /**
     * Determine whether progress output was enabled for the extension.
     *
     * @param \PHPUnit\Runner\Extension\ParameterCollection $parameterCollection
     * @return bool
     */
    private function isProgressEnabled(ParameterCollection $parameterCollection): bool
    {
        if (!$parameterCollection->has('progress')) {
            return true;
        }

        return filter_var(
            $parameterCollection->get('progress'),
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE,
        ) ?? true;
    }
}
