import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import AuctionImageGallery from "./AuctionImageGallery.vue";
import type { AuctionImage } from "../../types";

const images: AuctionImage[] = [
    { id: 1, path: "a.jpg", url: "/img/a.jpg" },
    { id: 2, path: "b.jpg", url: "/img/b.jpg" },
    { id: 3, path: "c.jpg", url: "/img/c.jpg" },
];

function mountGallery(list: AuctionImage[], activeImage = 0) {
    return mount(AuctionImageGallery, {
        props: {
            images: list,
            title: "Laptop",
            activeImage,
            "onUpdate:activeImage": () => {},
        },
    });
}

describe("AuctionImageGallery", () => {
    it("renders nothing when there are no images", () => {
        const wrapper = mountGallery([]);

        expect(wrapper.find("img").exists()).toBe(false);
    });

    it("shows the active image with the auction title as alt text", () => {
        const wrapper = mountGallery(images, 1);
        const main = wrapper.find("img");

        expect(main.attributes("src")).toBe("/img/b.jpg");
        expect(main.attributes("alt")).toBe("Laptop");
    });

    it("hides the thumbnail strip for a single image", () => {
        const wrapper = mountGallery(images.slice(0, 1));

        expect(wrapper.findAll("button")).toHaveLength(0);
    });

    it("highlights the active thumbnail and emits the index of a clicked one", async () => {
        const wrapper = mountGallery(images, 0);
        const thumbs = wrapper.findAll("button");

        expect(thumbs).toHaveLength(3);
        expect(thumbs[0].classes()).toContain("border-blue-500");
        expect(thumbs[2].classes()).not.toContain("border-blue-500");

        await thumbs[2].trigger("click");

        expect(wrapper.emitted("update:activeImage")).toEqual([[2]]);
    });
});
