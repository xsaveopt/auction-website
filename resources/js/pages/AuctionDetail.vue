<script setup>
import { ref, inject, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '../api.js';
import { HEARTBEAT_INTERVAL_MS } from '../presence.js';

const router = useRouter();

const props = defineProps({ id: String });
const user = inject('user');
const schedule = inject('schedule');
const auction = ref(null);
const bidAmount = ref('');
const bidQuantity = ref(1);
const error = ref('');
const loading = ref(true);
const activeImage = ref(0);

const myBid = computed(() => {
    if (!user.value || !auction.value) return null;
    return auction.value.bids.find(b => b.user.id === user.value.id);
});

const selectedBidTotal = computed(() => Number(bidAmount.value || 0) * Number(bidQuantity.value || 0));

async function load(showLoading = false) {
    if (showLoading) loading.value = true;

    const data = await api(`/auctions/${props.id}`);
    auction.value = data.auction;
    activeImage.value = Math.min(activeImage.value, Math.max(data.auction.images.length - 1, 0));
    const my = data.auction.bids.find(b => b.user.id === user.value?.id);
    bidAmount.value = my
        ? (Number(my.amount) + 1).toFixed(2)
        : (Number(data.auction.starting_price)).toFixed(2);
    bidQuantity.value = my ? my.quantity : 1;
    loading.value = false;
}

async function deleteAuction() {
    if (!confirm('Are you sure you want to delete this auction?')) return;
    try {
        await api(`/auctions/${props.id}`, { method: 'DELETE' });
        router.push('/');
    } catch (e) {
        error.value = e.data?.message || 'Failed to delete auction.';
    }
}

async function withdrawBid() {
    error.value = '';
    try {
        await api(`/auctions/${props.id}/bids`, { method: 'DELETE' });
        await load();
    } catch (e) {
        error.value = e.data?.message || 'Failed to withdraw bid.';
    }
}

async function placeBid() {
    error.value = '';
    try {
        await api(`/auctions/${props.id}/bids`, {
            method: 'POST',
            body: JSON.stringify({
                amount: Number(bidAmount.value),
                quantity: Number(bidQuantity.value),
            }),
        });
        await load();
    } catch (e) {
        error.value = e.data?.message || e.data?.errors?.amount?.[0] || e.data?.errors?.quantity?.[0] || 'Failed to place bid.';
    }
}

function formatDate(d) {
    return new Date(d).toLocaleString();
}

function formatMoney(value) {
    return Number(value).toFixed(2);
}

function watchingText(count) {
    return `${count} currently watching`;
}

let refreshInterval;
onMounted(async () => {
    await load(true);
    refreshInterval = setInterval(load, HEARTBEAT_INTERVAL_MS);
});
watch(() => props.id, async () => {
    activeImage.value = 0;
    await load(true);
});
onUnmounted(() => clearInterval(refreshInterval));
</script>

<template>
    <div v-if="loading" class="text-gray-500">Loading...</div>
    <div v-else-if="auction" class="space-y-6">
        <div class="bg-white rounded shadow p-6">
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-bold">{{ auction.title }}</h1>
                    <p class="text-gray-500 text-sm mt-1">Listed by {{ auction.seller.username }}</p>
                    <p class="mt-2 inline-flex rounded-full bg-amber-50 px-3 py-1 text-sm font-medium text-amber-700">
                        {{ watchingText(auction.watcher_count) }}
                    </p>
                </div>
                <div v-if="user?.is_admin" class="flex gap-2">
                    <router-link :to="`/auctions/${auction.id}/edit`"
                        class="text-sm text-blue-600 hover:text-blue-800 border border-blue-200 rounded px-3 py-1">
                        Edit
                    </router-link>
                    <button @click="deleteAuction"
                        class="text-sm text-red-600 hover:text-red-800 border border-red-200 rounded px-3 py-1">
                        Delete
                    </button>
                </div>
            </div>

            <div v-if="auction.images.length" class="mt-4">
                <img :src="auction.images[activeImage].url" :alt="auction.title"
                    class="w-full max-h-96 object-contain rounded bg-gray-50" />
                <div v-if="auction.images.length > 1" class="flex gap-2 mt-2">
                    <button v-for="(img, i) in auction.images" :key="img.id"
                        @click="activeImage = i"
                        class="w-16 h-16 rounded overflow-hidden border-2"
                        :class="i === activeImage ? 'border-blue-500' : 'border-transparent opacity-60 hover:opacity-100'">
                        <img :src="img.url" class="w-full h-full object-cover" />
                    </button>
                </div>
            </div>

            <p class="mt-4 text-gray-700 whitespace-pre-line">{{ auction.description }}</p>
            <div class="mt-4 flex flex-wrap items-center gap-6 text-sm">
                <div>
                    <span class="text-gray-500">Starting price:</span>
                    <span class="ml-1">${{ Number(auction.starting_price).toFixed(2) }} / item</span>
                </div>
                <div v-if="auction.quantity > 1">
                    <span class="text-gray-500">Available:</span>
                    <span class="ml-1 font-semibold text-purple-700">{{ auction.quantity }} items</span>
                    <span class="text-gray-400 ml-1">(max {{ auction.max_per_bidder }} per person)</span>
                </div>
                <div>
                    <span class="text-gray-500">Ends:</span>
                    <span class="ml-1">{{ formatDate(auction.ends_at) }}</span>
                </div>
            </div>
            <div v-if="auction.quantity > 1" class="mt-3 text-sm text-gray-500">
                <p>Items allocated top-down by bid price. All winners pay the same clearing price (the lowest winning bid).</p>
                <p class="mt-1">Your bid amount is per item. Entering $10.00 for 4 items means a total commitment of $40.00.</p>
                <p v-if="auction.max_per_bidder > 1" class="mt-1">You can bid for up to {{ auction.max_per_bidder }} items. Bids may be partially filled if stock runs out.</p>
                <p v-if="auction.bid_count > 0" class="mt-2 font-medium text-green-700">
                    Clearing price: ${{ formatMoney(auction.current_price) }} / item
                    · {{ auction.items_allocated }} / {{ auction.quantity }} allocated
                </p>
            </div>
        </div>

        <div v-if="auction.is_active && user && user.id !== auction.seller.id" class="bg-white rounded shadow p-6">
            <h2 class="text-lg font-semibold mb-3">
                {{ myBid ? 'Update Your Bid' : 'Place a Bid' }}
            </h2>
            <div v-if="schedule && !schedule.is_open"
                class="bg-orange-50 border border-orange-200 rounded p-3 mb-3 text-orange-700 text-sm">
                Bidding is closed during office hours ({{ schedule.closed_start }} – {{ schedule.closed_end }}).
                You can bid again after {{ schedule.closed_end }}.
            </div>
            <template v-else>
                <div v-if="myBid" class="flex items-center justify-between mb-3">
                    <p class="text-sm text-gray-500">
                        Your current bid: <span class="font-bold text-green-700">${{ formatMoney(myBid.amount) }}</span>
                        <span v-if="auction.max_per_bidder > 1"> for {{ myBid.quantity }} item{{ myBid.quantity !== 1 ? 's' : '' }}</span>
                        <span v-if="auction.max_per_bidder > 1" class="ml-1">(up to ${{ formatMoney(myBid.amount * myBid.quantity) }} total)</span>
                        <span v-if="myBid.won_quantity > 0" class="text-green-600 ml-1">(winning {{ myBid.won_quantity }})</span>
                    </p>
                    <button @click="withdrawBid" class="text-sm text-red-600 hover:underline">
                        Withdraw bid
                    </button>
                </div>
                <div v-if="error" class="bg-red-100 text-red-700 p-3 rounded mb-3">{{ error }}</div>
                <form @submit.prevent="placeBid" class="flex flex-wrap gap-3 items-end">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Price per item</label>
                        <div class="flex items-center">
                            <span class="text-gray-500 mr-1">$</span>
                            <input v-model="bidAmount" type="number" step="0.01" min="0.01" required
                                class="border rounded px-3 py-2 w-32" />
                        </div>
                    </div>
                    <div v-if="auction.max_per_bidder > 1">
                        <label class="block text-xs text-gray-500 mb-1">Quantity</label>
                        <input v-model="bidQuantity" type="number" min="1" :max="auction.max_per_bidder" required
                            class="border rounded px-3 py-2 w-20" />
                    </div>
                    <div v-if="auction.max_per_bidder > 1" class="basis-full text-sm text-gray-500">
                        Your price is per item, so bidding ${{ formatMoney(bidAmount) }} for {{ bidQuantity }} item{{ Number(bidQuantity) !== 1 ? 's' : '' }}
                        means a maximum total of ${{ formatMoney(selectedBidTotal) }}.
                    </div>
                    <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                        {{ myBid ? 'Update Bid' : 'Bid' }}
                    </button>
                </form>
            </template>
        </div>
        <div v-else-if="!auction.is_active" class="bg-yellow-50 border border-yellow-200 rounded p-4 text-yellow-800">
            This auction has ended.
        </div>

        <div class="bg-white rounded shadow p-6">
            <h2 class="text-lg font-semibold mb-3">
                Bids ({{ auction.bid_count }})
                <span v-if="auction.quantity > 1" class="text-sm font-normal text-gray-500">
                    — {{ auction.items_allocated }} / {{ auction.quantity }} allocated
                </span>
            </h2>
            <p v-if="auction.bids.length === 0" class="text-gray-500">No bids yet.</p>
            <ul v-else class="divide-y">
                <li v-for="bid in auction.bids" :key="bid.id"
                    class="py-2 flex items-center justify-between"
                    :class="bid.won_quantity > 0 ? 'bg-green-50 -mx-2 px-2 rounded' : ''">
                    <div class="flex items-center gap-2">
                        <span class="font-medium">{{ bid.user.username }}</span>
                        <span v-if="bid.user.id === user?.id" class="text-xs text-blue-600">(you)</span>
                        <span v-if="auction.max_per_bidder > 1" class="text-gray-400 text-xs">wants {{ bid.quantity }}</span>
                        <span class="text-gray-400 text-xs">{{ formatDate(bid.created_at) }}</span>
                    </div>
                    <div class="text-right">
                        <span class="font-bold" :class="bid.won_quantity > 0 ? 'text-green-700' : 'text-gray-500'">
                            ${{ Number(bid.amount).toFixed(2) }}
                        </span>
                        <span v-if="bid.won_quantity > 0 && auction.quantity > 1"
                            class="block text-xs text-green-600">
                            wins {{ bid.won_quantity }} @ ${{ Number(auction.current_price).toFixed(2) }}
                        </span>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>
