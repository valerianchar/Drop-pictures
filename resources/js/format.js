const UNITS = ['o', 'Ko', 'Mo', 'Go', 'To'];

/** « 14,2 Mo », « 480 Mo » — même règle que App\Support\FileSize. */
export function formatBytes(bytes) {
    if (bytes < 1024) {
        return `${bytes} o`;
    }

    const power = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), UNITS.length - 1);
    const value = bytes / 1024 ** power;
    const decimals = value < 10 ? 1 : 0;

    return `${value.toLocaleString('fr-FR', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })} ${UNITS[power]}`;
}

export function plural(count, singular, pluralForm = `${singular}s`) {
    return `${count.toLocaleString('fr-FR')} ${count > 1 ? pluralForm : singular}`;
}
