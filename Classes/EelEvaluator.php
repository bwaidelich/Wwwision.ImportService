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
 */
#[Flow\Scope('singleton')]
final class EelEvaluator
{
    /**
     * @var EelEvaluatorInterface
     */
    #[Flow\Inject(lazy: false)]
    protected EelEvaluatorInterface $eelEvaluator;

    #[Flow\InjectConfiguration(path: 'defaultContext', package: 'Neos.Fusion')]
    protected array|null $eelDefaultContextConfiguration = null;

    private array|null $eelDefaultContextVariables = null;

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
        } catch (\Throwable $exception) {
            throw new \RuntimeException(sprintf('Error while evaluating Eel expression: %s', $expression), 1706890254, $exception);
        }
    }

}
