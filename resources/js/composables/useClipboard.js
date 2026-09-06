import { ref } from 'vue';

/**
 * Copie un texte et le signale brièvement (« Copié »). Le repli par sélection
 * couvre les contextes sans API Clipboard (HTTP en local, vieux WebView).
 */
export function useClipboard() {
    const copied = ref(false);
    let timer = null;

    async function copy(text) {
        try {
            await navigator.clipboard.writeText(text);
        } catch {
            const area = document.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.opacity = '0';
            document.body.appendChild(area);
            area.select();
            document.execCommand('copy');
            document.body.removeChild(area);
        }

        copied.value = true;
        clearTimeout(timer);
        timer = setTimeout(() => (copied.value = false), 2000);
    }

    return { copied, copy };
}
