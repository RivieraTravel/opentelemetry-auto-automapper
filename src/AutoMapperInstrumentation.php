<?php

declare(strict_types=1);

namespace Riviera\Contrib\Instrumentation\AutoMapper;

use AutoMapperPlus\AutoMapperInterface;
use AutoMapperPlus\DataType;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use function OpenTelemetry\Instrumentation\hook;
use OpenTelemetry\SemConv\TraceAttributes;
use Throwable;

class AutoMapperInstrumentation
{
    public const NAME = 'automapper';

    public static function register(): void
    {
        $instrumentation = new CachedInstrumentation('uk.co.rivieratravel.contrib.automapper');
        
        hook(
            class: AutoMapperInterface::class,
            function: 'map',
            pre: static function (AutoMapperInterface $mapper, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation) {
                $source = $params[0] ?? null;
                $targetClass = $params[1];
                $context = $params[3] ?? null;

                $sourceType = 'unknown';
                if ($source && is_array($source)) {
                    $sourceType = DataType::ARRAY;
                } else if ($source && is_object($source)) {
                    $sourceType = $source::class;
                }

                $builder = $instrumentation->tracer()
                   ->spanBuilder(
                        sprintf(
                            'map %s -> %s ',
                            $sourceType,
                            $targetClass
                        )
                    )
                   ->setAttribute(TraceAttributes::CODE_FUNCTION, $function)
                   ->setAttribute(TraceAttributes::CODE_NAMESPACE, $class)
                   ->setAttribute(TraceAttributes::CODE_FILEPATH, $filename)
                   ->setAttribute(TraceAttributes::CODE_LINENO, $lineno);
    
                if ($source) {
                    $builder->setAttribute('automapper.source.class', $sourceType);
                }
                $builder->setAttribute('automapper.target.class', $targetClass);
    
                $parent = Context::getCurrent();
                $span = $builder->startSpan();
                Context::storage()->attach($span->storeInContext($parent));
            },
            post: static function (AutoMapperInterface $mapper, array $params, $return, ?Throwable $exception) {
                $scope = Context::storage()->scope();
                if (!$scope) {
                    return;
                }

                $scope->detach();
                $span = Span::fromContext($scope->context());

                if ($exception) {
                    $span->recordException($exception, [TraceAttributes::EXCEPTION_ESCAPED => true]);
                    $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
                }

                $span->end();
            }
        );
    }
}
