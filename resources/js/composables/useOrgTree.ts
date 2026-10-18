import { computed, ref, toValue, type MaybeRefOrGetter } from 'vue';
import type { OrgPerson, OrgTeam, OrgTreeData } from '@/types';

const normalize = (value: string) => value.toLocaleLowerCase('id').trim();

/** State of the organisation tree: search, which teams are open and which person is picked. */
export function useOrgTree(tree: MaybeRefOrGetter<OrgTreeData>) {
    const query = ref('');
    const selectedId = ref<number | null>(null);
    const collapsed = ref(new Set<number>());

    const people = (team: OrgTeam): OrgPerson[] => [...(team.leader ? [team.leader] : []), ...team.members];

    const teams = computed<OrgTeam[]>(() => {
        const q = normalize(query.value);
        const all = toValue(tree).teams;
        if (!q) return all;

        return all
            .map((team) => {
                if (normalize(team.name).includes(q)) return team;
                const leader = team.leader && normalize(team.leader.name).includes(q) ? team.leader : null;
                const members = team.members.filter((m) => normalize(m.name).includes(q));
                return leader || members.length ? { ...team, leader, members } : null;
            })
            .filter((team): team is OrgTeam => team !== null);
    });

    /** Teams a person belongs to, to show that one person can sit in several teams. */
    const teamsOf = computed(() => {
        const map = new Map<number, string[]>();
        for (const team of toValue(tree).teams) {
            for (const person of people(team)) map.set(person.id, [...(map.get(person.id) ?? []), team.name]);
        }
        return map;
    });

    const selected = computed(() => {
        if (selectedId.value === null) return null;
        const names = teamsOf.value.get(selectedId.value);
        if (!names) return null;
        const person = toValue(tree).teams.flatMap(people).find((p) => p.id === selectedId.value) ?? null;
        return person ? { person, teams: names } : null;
    });

    const isOpen = (teamId: number) => query.value !== '' || !collapsed.value.has(teamId);

    function toggle(teamId: number) {
        const next = new Set(collapsed.value);
        if (next.has(teamId)) next.delete(teamId); else next.add(teamId);
        collapsed.value = next;
    }

    function setAll(open: boolean) {
        collapsed.value = open ? new Set() : new Set(toValue(tree).teams.map((t) => t.id));
    }

    function select(personId: number) {
        selectedId.value = selectedId.value === personId ? null : personId;
    }

    return { query, teams, selected, selectedId, isOpen, toggle, setAll, select, teamsOf };
}
