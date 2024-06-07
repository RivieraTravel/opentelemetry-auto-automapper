<?php

declare(strict_types=1);

use Riviera\Contrib\Instrumentation\AutoMapper\AutoMapperInstrumentation;
use OpenTelemetry\SDK\Sdk;

if (class_exists(Sdk::class) && Sdk::isInstrumentationDisabled(AutoMapperInstrumentation::NAME) === true) {
    return;
}

if (extension_loaded('opentelemetry') === false) {
    trigger_error('The opentelemetry extension must be loaded in order to autoload the OpenTelemetry AutoMapper auto-instrumentation', E_USER_WARNING);

    return;
}

AutoMapperInstrumentation::register();
