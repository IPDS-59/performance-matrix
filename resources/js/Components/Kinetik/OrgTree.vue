<script setup lang="ts">
import { ChevronDown, ChevronRight, Crown, Users } from 'lucide-vue-next';
import type { OrgPerson, OrgTeam, OrgTreeData } from '@/types';

defineProps<{
    tree: OrgTreeData;
    teams: OrgTeam[];
    selectedId: number | null;
    isOpen: (teamId: number) => boolean;
}>();
const emit = defineEmits<{ toggle: [teamId: number]; select: [personId: number] }>();

const initials = (name: string) => name.split(' ').filter(Boolean).slice(0, 2).map((p) => p[0]).join('').toUpperCase();
const count = (team: OrgTeam) => team.members.length + (team.leader ? 1 : 0);
</script>

<template>
    <div class="org-tree">
        <!-- Level 1, always pinned to the top while the list scrolls -->
        <header class="org-root">
            <span class="ava head">{{ tree.head ? initials(tree.head.name) : 'BP' }}</span>
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold">{{ tree.satker }}</p>
                <p class="truncate text-xs opacity-80">
                    <template v-if="tree.head">{{ tree.head.name }} · Kepala · </template>{{ $n(tree.employee_count) }} pegawai · {{ tree.teams.length }} tim kerja
                </p>
            </div>
        </header>

        <section v-for="team in teams" :key="team.id" class="org-team">
            <!-- Level 2, pinned under the satker while its members scroll -->
            <button type="button" class="org-team-head" :aria-expanded="isOpen(team.id)" @click="emit('toggle', team.id)">
                <component :is="isOpen(team.id) ? ChevronDown : ChevronRight" class="h-4 w-4 shrink-0" aria-hidden="true" />
                <Users class="h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
                <span class="truncate text-sm font-semibold">{{ team.name }}</span>
                <span class="ml-auto shrink-0 rounded-full bg-white px-2 py-0.5 text-xs font-semibold tabular-nums text-primary">{{ count(team) }} orang</span>
            </button>

            <div v-if="isOpen(team.id)" class="org-body">
                <div v-if="team.leader" class="org-leader">
                    <button type="button" class="node leader" :data-sel="selectedId === team.leader.id" :aria-pressed="selectedId === team.leader.id" @click="emit('select', team.leader.id)">
                        <span class="ava leader"><Crown class="h-4 w-4" aria-hidden="true" /></span>
                        <span class="name">{{ team.leader.name }}</span>
                        <span class="role">Ketua tim</span>
                    </button>
                </div>
                <div v-if="team.members.length" class="org-kids">
                    <div v-for="m in team.members" :key="m.id" class="org-kid">
                        <button type="button" class="node" :data-sel="selectedId === m.id" :aria-pressed="selectedId === m.id" :title="(m as OrgPerson).position ?? undefined" @click="emit('select', m.id)">
                            <span class="ava">{{ initials(m.name) }}</span>
                            <span class="name">{{ m.name }}</span>
                            <span class="role">Anggota</span>
                        </button>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<style scoped>
.org-tree { --rule: #cbd5e1; }
.org-root { position: sticky; top: 0; z-index: 30; display: flex; align-items: center; gap: .75rem; padding: .6rem 1rem; background: #1B4B8C; color: #fff; border-radius: .75rem .75rem 0 0; }
.org-team { position: relative; }
.org-team-head { position: sticky; top: 3.75rem; z-index: 20; display: flex; width: 100%; align-items: center; gap: .5rem; padding: .55rem 1rem; background: #E8EFF8; border-bottom: 1px solid #c9d6ea; box-shadow: 0 2px 4px rgb(27 75 140 / .08); text-align: left; }
.org-team-head:hover { background: #dfe9f6; }
.org-body { position: relative; padding: 1.25rem 1rem 1.25rem 1.5rem; }
.org-body::before { content: ''; position: absolute; left: 1.5rem; top: 0; bottom: 1.25rem; width: 1px; background: var(--rule); }
.org-leader { position: relative; padding-left: 1.5rem; margin-bottom: 1rem; }
.org-leader::before { content: ''; position: absolute; left: 0; top: 50%; width: 1.5rem; height: 1px; background: var(--rule); }
.org-kids { position: relative; display: grid; grid-template-columns: repeat(auto-fill, minmax(10.5rem, 1fr)); gap: .6rem; padding-left: 1.5rem; }
.org-kid { position: relative; }
.org-kid::before { content: ''; position: absolute; left: -1.5rem; top: 1.45rem; width: 1.5rem; height: 1px; background: var(--rule); }
.org-kid:not(:first-child)::before { display: none; }
.node { position: relative; display: flex; width: 100%; flex-direction: column; align-items: center; gap: .15rem; padding: .7rem .5rem; border: 1px solid #e2e8f0; border-radius: .9rem; background: #fff; text-align: center; box-shadow: 0 1px 2px rgb(15 23 42 / .06); transition: transform .2s, box-shadow .2s, border-color .2s; }
.node:hover { transform: translateY(-2px); box-shadow: 0 6px 14px rgb(15 23 42 / .1); }
.node[data-sel='true'] { border-color: #F28C1B; box-shadow: 0 0 0 2px #F28C1B; }
.node:focus-visible { outline: 2px solid #1B4B8C; outline-offset: 3px; }
.node.leader { width: 14rem; flex-direction: row; justify-content: flex-start; gap: .6rem; padding: .55rem .8rem; text-align: left; background: #FFF8EE; border-color: #F28C1B66; }
.node.leader .name { flex: 1; }
.name { font-size: .82rem; font-weight: 600; line-height: 1.2; overflow-wrap: anywhere; }
.role { font-size: .68rem; color: #64748b; }
.node.leader .role { color: #9a5a00; font-weight: 700; }
.ava { display: inline-flex; width: 2rem; height: 2rem; flex: none; align-items: center; justify-content: center; border-radius: 9999px; background: #dfe6f1; color: #1B4B8C; font-size: .7rem; font-weight: 700; }
.ava.head { background: #F28C1B; color: #fff; }
.ava.leader { background: #F28C1B; color: #fff; }
@media (prefers-reduced-motion: reduce) { .node { transition: none; } }
</style>
