import { inject, type ComputedRef, type Ref } from "vue";
import type { CurrentRound, HeartbeatData, NotifyFn, OnLoginFn, Schedule, User } from "./types";

function injectStrict<T>(key: string): T {
    const value = inject<T>(key);

    if (value === undefined) {
        throw new Error(`Missing provided value for injection key: ${key}`);
    }

    return value;
}

export const injectUser = (): Ref<User | null> => injectStrict("user");
export const injectOnLogin = (): OnLoginFn => injectStrict("onLogin");
export const injectSchedule = (): ComputedRef<Schedule | null> => injectStrict("schedule");
export const injectCurrencySymbol = (): Ref<string> => injectStrict("currencySymbol");
export const injectHeartbeatData = (): Ref<HeartbeatData | null> => injectStrict("heartbeatData");
export const injectNow = (): Ref<Date> => injectStrict("now");
export const injectNotify = (): NotifyFn => injectStrict("notify");
export const injectCurrentRound = (): Ref<CurrentRound> => injectStrict("currentRound");
export const injectNotifyOptional = (): NotifyFn | undefined => inject<NotifyFn>("notify");
