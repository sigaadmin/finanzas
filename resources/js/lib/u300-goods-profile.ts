export type U300TechnicalSheetGood = {
    unit: string;
    description: string;
    minimum_quantity: string;
    unit_price: string;
    specifications: string;
    reference_photo: File | null;
    reference_photo_path: string;
};

export function emptyU300TechnicalSheetGood(): U300TechnicalSheetGood {
    return {
        unit: '',
        description: '',
        minimum_quantity: '',
        unit_price: '',
        specifications: '',
        reference_photo: null,
        reference_photo_path: '',
    };
}

function numberFromMoney(value: string): string {
    const normalized = value.replace(/[$,\s]/g, '');
    const amount = Number(normalized);

    return Number.isFinite(amount) && amount > 0 ? String(amount) : '';
}

function valueAfterPrefix(line: string, prefix: string): string {
    return line.startsWith(prefix) ? line.replace(prefix, '').trim() : '';
}

export function parseU300GoodsProfile(
    profile: string | null | undefined,
): U300TechnicalSheetGood[] {
    if (!profile) {
        return [emptyU300TechnicalSheetGood()];
    }

    const goods = profile
        .split(/\n{2,}/)
        .map((block): U300TechnicalSheetGood => {
            const lines = block
                .split('\n')
                .map((line) => line.trim())
                .filter(Boolean);
            const description = lines[0]?.replace(/^\d+\.\s*/, '') ?? '';
            const specificationsLines: string[] = [];
            let unit = '';
            let minimumQuantity = '';
            let unitPrice = '';
            let referencePhotoPath = '';
            let isReadingSpecifications = false;

            for (const line of lines.slice(1)) {
                if (line.startsWith('Unidad de medida:')) {
                    unit = valueAfterPrefix(line, 'Unidad de medida:');
                    isReadingSpecifications = false;

                    continue;
                }

                if (line.startsWith('Cantidad mínima:')) {
                    minimumQuantity = valueAfterPrefix(
                        line,
                        'Cantidad mínima:',
                    );
                    isReadingSpecifications = false;

                    continue;
                }

                if (line.startsWith('Precio unitario:')) {
                    unitPrice = numberFromMoney(
                        valueAfterPrefix(line, 'Precio unitario:'),
                    );
                    isReadingSpecifications = false;

                    continue;
                }

                if (line.startsWith('Especificaciones:')) {
                    specificationsLines.push(
                        valueAfterPrefix(line, 'Especificaciones:'),
                    );
                    isReadingSpecifications = true;

                    continue;
                }

                if (line.startsWith('Foto de referencia:')) {
                    referencePhotoPath = valueAfterPrefix(
                        line,
                        'Foto de referencia:',
                    );
                    isReadingSpecifications = false;

                    continue;
                }

                if (isReadingSpecifications) {
                    specificationsLines.push(line);
                }
            }

            return {
                unit,
                description:
                    description === 'Bien sin descripción' ? '' : description,
                minimum_quantity: minimumQuantity,
                unit_price: unitPrice,
                specifications: specificationsLines.join('\n').trim(),
                reference_photo: null,
                reference_photo_path: referencePhotoPath,
            };
        })
        .filter(
            (good) =>
                good.unit !== '' ||
                good.description !== '' ||
                good.minimum_quantity !== '' ||
                good.unit_price !== '' ||
                good.specifications !== '' ||
                good.reference_photo_path !== '',
        );

    return goods.length > 0 ? goods : [emptyU300TechnicalSheetGood()];
}
