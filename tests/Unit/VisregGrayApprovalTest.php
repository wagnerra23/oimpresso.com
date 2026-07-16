<?php

declare(strict_types=1);

use Tests\Browser\Support\VisregThreshold;

it('bloqueia zona cinza sem aprovação e libera somente o label explícito', function () {
    $gray = [['screen' => 'Sells/Create', 'ratio' => 0.01, 'diffView' => null]];

    expect(VisregThreshold::grayZoneRequiresApproval([], '0'))->toBeFalse()
        ->and(VisregThreshold::grayZoneRequiresApproval($gray, '0'))->toBeTrue()
        ->and(VisregThreshold::grayZoneRequiresApproval($gray, '1'))->toBeFalse();
});
