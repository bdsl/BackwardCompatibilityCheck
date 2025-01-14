<?php

declare(strict_types=1);

namespace RoaveTestAsset;

enum EnumWithCasesBeingChanged
{
    case March;
    case April;
    case May;
    case June;
    case July;
    case August;
    case September;
    case October;
    case November;
    Case December;

    /**
     * @internal - may be removed without notice
     */
    case FakeMonth;
}
