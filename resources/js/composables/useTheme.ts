import { computed, ref, watchEffect } from "vue";

type Theme = "dark" | "light";

const mql = window.matchMedia("(prefers-color-scheme: dark)");
const systemDark = ref(mql.matches);

mql.addEventListener("change", (e: MediaQueryListEvent) => {
    systemDark.value = e.matches;
});

const initial = localStorage.getItem("theme");
const chosenTheme = ref<Theme | null>(initial === "dark" || initial === "light" ? initial : null);

const isDark = computed(() =>
    chosenTheme.value ? chosenTheme.value === "dark" : systemDark.value,
);

watchEffect(() => {
    document.documentElement.classList.toggle("dark", isDark.value);
});

export function useTheme() {
    function toggleTheme() {
        chosenTheme.value = isDark.value ? "light" : "dark";
        localStorage.setItem("theme", chosenTheme.value);
    }

    return { isDark, toggleTheme };
}
