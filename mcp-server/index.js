#!/usr/bin/env node

import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { z } from "zod";

// --- Configuration from environment ---
const AUCTION_URL = (process.env.AUCTION_URL || "").replace(/\/+$/, "");
const MCP_API_KEY = process.env.MCP_API_KEY || "";

if (!AUCTION_URL || !MCP_API_KEY) {
    console.error("Required env vars: AUCTION_URL, MCP_API_KEY");
    process.exit(1);
}

// --- API client ---
async function apiCall(method, path, body) {
    const res = await fetch(`${AUCTION_URL}/api${path}`, {
        method,
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            Authorization: `Bearer ${MCP_API_KEY}`,
        },
        body: body ? JSON.stringify(body) : undefined,
    });

    const data = await res.json();

    if (!res.ok) {
        throw new Error(data.message || `API error ${res.status}`);
    }

    return data;
}

// --- MCP Server ---
const server = new McpServer({
    name: "auction-pricing",
    version: "1.0.0",
});

// --- Tool: list_auctions ---
server.tool(
    "list_auctions",
    "List all active auctions with their titles, descriptions, starting prices, current highest bids, bid counts, and quantities. Use this to see what items need pricing research.",
    {},
    async () => {
        try {
            const data = await apiCall("GET", "/auctions");
            const summary = data.auctions.map((a) => ({
                id: a.id,
                title: a.title,
                description: a.description,
                starting_price: a.starting_price,
                current_price: a.current_price,
                quantity: a.quantity,
                max_per_bidder: a.max_per_bidder,
                bid_count: a.bid_count,
                ends_at: a.ends_at,
                is_active: a.is_active,
            }));
            return { content: [{ type: "text", text: JSON.stringify(summary, null, 2) }] };
        } catch (e) {
            return { content: [{ type: "text", text: `Error: ${e.message}` }], isError: true };
        }
    },
);

// --- Tool: get_auction ---
server.tool(
    "get_auction",
    "Get detailed information about a specific auction, including all bids. Use this to understand demand and current pricing for a specific item.",
    { auction_id: z.number().int().positive().describe("The auction ID") },
    async ({ auction_id }) => {
        try {
            const data = await apiCall("GET", `/auctions/${auction_id}`);
            return { content: [{ type: "text", text: JSON.stringify(data.auction, null, 2) }] };
        } catch (e) {
            return { content: [{ type: "text", text: `Error: ${e.message}` }], isError: true };
        }
    },
);

// --- Tool: update_starting_price ---
server.tool(
    "update_starting_price",
    "Update the starting price of an active auction. Use this after researching market prices to set an appropriate starting price. Only works on active auctions that have no bids yet.",
    {
        auction_id: z.number().int().positive().describe("The auction ID"),
        starting_price: z.number().positive().describe("The new starting price based on market research"),
        reasoning: z.string().describe("Brief explanation of why this price was chosen (e.g. market comparisons)"),
    },
    async ({ auction_id, starting_price, reasoning }) => {
        try {
            // Fetch current auction to validate and get existing data for the PUT
            const { auction } = await apiCall("GET", `/auctions/${auction_id}`);

            if (!auction.is_active) {
                return {
                    content: [{ type: "text", text: `Auction "${auction.title}" is not active.` }],
                    isError: true,
                };
            }

            if (auction.bid_count > 0) {
                return {
                    content: [
                        {
                            type: "text",
                            text: `Auction "${auction.title}" already has ${auction.bid_count} bid(s). Cannot change starting price after bids have been placed.`,
                        },
                    ],
                    isError: true,
                };
            }

            const rounded = Math.round(starting_price * 100) / 100;

            // PUT requires all fields — send existing values with updated price
            await apiCall("PUT", `/auctions/${auction_id}`, {
                title: auction.title,
                description: auction.description,
                starting_price: rounded,
                quantity: auction.quantity,
                max_per_bidder: auction.max_per_bidder,
                ends_at: auction.ends_at,
            });

            return {
                content: [
                    {
                        type: "text",
                        text: `Updated "${auction.title}" starting price: ${auction.starting_price} → ${rounded}\n\nReasoning: ${reasoning}`,
                    },
                ],
            };
        } catch (e) {
            return { content: [{ type: "text", text: `Error: ${e.message}` }], isError: true };
        }
    },
);

// --- Start server ---
const transport = new StdioServerTransport();
await server.connect(transport);
