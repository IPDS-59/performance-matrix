export {};

declare module 'vue' {
    interface ComponentCustomProperties {
        /** Indonesian number format (1.293), available in every template. */
        $n: (value: unknown) => string;
    }
}
