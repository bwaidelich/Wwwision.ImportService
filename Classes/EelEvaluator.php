<?php
declare(strict_types=1);
namespace Wwwision\ImportService;

use Neos\Eel\EelEvaluatorInterface;
use Neos\Eel\Exception;
use Neos\Eel\Package as EelPackage;
use Neos\Eel\Utility as EelUtility;
use Neos\Flow\Annotations as Flow;

/**
 * A facade for the somewhat quirky Neos\Eel\Utility class that allows to detect and evaluate Eel expressions
 *
 * @Flow\Scope("singleton")
 */
final class EelEvaluator
{
    /**
     * @Flow\Inject(lazy=false)
     * @var EelEvaluatorInterface
     */
    protected $eelEvaluator;

    /**
     * @Flow\InjectConfiguration(package="Neos.Fusion", path="defaultContext")
     * @var array
     */
    protected $eelDefaultContextConfiguration;

    /**
     * @var array
     */
    private $eelDefaultContextVariables;

    public function isEelExpression(string $expression): bool
    {
        return preg_match(EelPackage::EelExpressionRecognizer, $expression) !== 0;
    }

    public function evaluateIfExpression(string $expression, array $variables)
    {
        return $this->isEelExpression($expression) ? $this->evaluate($expression, $variables) : $expression;
    }

    public function evaluate(string $expression, array $variables)
    {
        if ($this->eelDefaultContextVariables === null) {
            $this->eelDefaultContextVariables = $this->eelDefaultContextConfiguration === null ? [] : EelUtility::getDefaultContextVariables($this->eelDefaultContextConfiguration);
        }
        try {
            return EelUtility::evaluateEelExpression($expression, $this->eelEvaluator, array_merge($this->eelDefaultContextVariables, $variables));
        } catch (Exception $exception) {
            throw new \RuntimeException(sprintf('The following Eel expression could not be evaluated: %s', $expression), 1558096953, $exception);
        }
    }

}
