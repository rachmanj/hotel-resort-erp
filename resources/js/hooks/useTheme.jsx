import { createContext, useContext, useState, useEffect } from 'react';

export const ThemeContext = createContext({
    isDark: true,
    toggleTheme: () => {},
});

export const useTheme = () => useContext(ThemeContext);

export function ThemeProvider({ children }) {
    const [isDark, setIsDark] = useState(() => {
        const stored = localStorage.getItem('theme');
        if (stored === 'light') return false;
        if (stored === 'dark') return true;
        return true;
    });

    useEffect(() => {
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
    }, [isDark]);

    const toggleTheme = () => setIsDark((prev) => !prev);

    return (
        <ThemeContext.Provider value={{ isDark, toggleTheme }}>
            {children}
        </ThemeContext.Provider>
    );
}