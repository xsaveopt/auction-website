import { describe, it, expect } from "vitest";
import { ApiError } from "./api";
import { apiError } from "./apiError";

describe("apiError", () => {
    it("ignores errors that are not API errors", () => {
        expect(apiError(new Error("offline"), "amount")).toBeUndefined();
        expect(apiError("boom")).toBeUndefined();
    });

    it("prefers the top-level message", () => {
        const error = new ApiError(422, {
            message: "Closed",
            errors: { amount: ["Too low."] },
        });
        expect(apiError(error, "amount")).toBe("Closed");
    });

    it("falls back to the first error of the first matching field", () => {
        const error = new ApiError(422, {
            errors: { quantity: ["Too many.", "Other."], amount: ["Too low."] },
        });
        expect(apiError(error, "username", "quantity", "amount")).toBe("Too many.");
    });

    it("returns undefined when nothing matches", () => {
        const error = new ApiError(422, { errors: { amount: ["Too low."] } });
        expect(apiError(error)).toBeUndefined();
        expect(apiError(error, "quantity")).toBeUndefined();
        expect(apiError(new ApiError(500, { message: "" }))).toBeUndefined();
    });
});
