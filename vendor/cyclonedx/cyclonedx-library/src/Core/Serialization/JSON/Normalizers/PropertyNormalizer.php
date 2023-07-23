<?php

declare(strict_types=1);

/*
 * This file is part of CycloneDX PHP Library.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * SPDX-License-Identifier: Apache-2.0
 * Copyright (c) OWASP Foundation. All Rights Reserved.
 */

namespace CycloneDX\Core\Serialization\JSON\Normalizers;

use CycloneDX\Core\Models\Property;
use CycloneDX\Core\Serialization\JSON\_BaseNormalizer;
use DomainException;

/**
 * @author jkowalleck
 */
class PropertyNormalizer extends _BaseNormalizer
{
    /**
     * @throws DomainException if property's name is empty
     */
    public function normalize(Property $property): array
    {
        $name = $property->getName();
        if ('' === $name) {
            // this implementation detail is optional
            throw new DomainException('empty name');
        }

        return [
            'name' => $name,
            'value' => $property->getValue(),
        ];
    }
}
