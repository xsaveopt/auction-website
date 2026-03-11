import { ref, watchEffect } from "vue";

const systemDark = ref(window.matchMedia("(prefers-color-scheme: dark)").matches);

const mql = window.matchMedia("(prefers-color-scheme: dark)");
mql.addEventListener("change", (e) => {
    systemDark.value = e.matches;
});

const stored = localStorage.getItem("theme");
const isDark = ref(stored ? stored === "dark" : systemDark.value);

watchEffect(() => {
    document.documentElement.classList.toggle("dark", isDark.value);
    localStorage.setItem("theme", isDark.value ? "dark" : "light");
});

export function useTheme() {
    function toggleTheme() {
        isDark.value = !isDark.value;
    }

    return { isDark, toggleTheme };
}
