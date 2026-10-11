import type { InjectionKey, Ref } from 'vue'

export type SelectValueType = string | number | null | undefined

export interface SelectContext {
    value: Ref<SelectValueType>
    open: Ref<boolean>
    /** value -> visible text, filled by the always-mounted hidden copy of the items. */
    labels: Map<string | number | null, string>
    disabled: Ref<boolean>
    select: (value: string | number | null) => void
}

export const selectKey: InjectionKey<SelectContext> = Symbol('select')
/** True inside the hidden copy of the items that only reports labels. */
export const collectingKey: InjectionKey<boolean> = Symbol('select-collecting')
