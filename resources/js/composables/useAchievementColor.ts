export function useAchievementColor() {
    function achievementColor(pct: number): string {
        if (pct >= 80) return 'text-green-600';
        if (pct >= 50) return 'text-yellow-500';
        return 'text-red-500';
    }

    function achievementBgClass(pct: number): string {
        if (pct >= 80) return 'bg-green-100 text-green-700';
        if (pct >= 50) return 'bg-yellow-100 text-yellow-700';
        return 'bg-red-100 text-red-700';
    }

    function progressVariant(pct: number): string {
        if (pct >= 80) return 'bg-green-500';
        if (pct >= 50) return 'bg-yellow-500';
        return 'bg-red-500';
    }

    function avgIconBgColor(pct: number): string {
        if (pct >= 80) return 'bg-green-100';
        if (pct >= 50) return 'bg-yellow-100';
        return 'bg-red-100';
    }

    function avgIconColor(pct: number): string {
        if (pct >= 80) return 'text-green-600';
        if (pct >= 50) return 'text-yellow-500';
        return 'text-red-500';
    }

    return { achievementColor, achievementBgClass, progressVariant, avgIconBgColor, avgIconColor };
}
