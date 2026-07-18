export function canSubmitLocalDataReset(value, expected, processing) {
    return !processing && value === expected;
}
