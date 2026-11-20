/** 可用的第三方登入 provider（已啟用且設定齊全）。 */
export interface SocialProviderOption {
    key: string;
    label: string;
}

/** social-login 模組共享給所有頁面的資料。 */
export interface SocialLoginSharedData {
    social_login: {
        providers: SocialProviderOption[];
    };
}

/** 使用者在某個平台綁定的第三方帳號；linked_at 為 ISO 8601。 */
export interface LinkedSocialAccount {
    email: string | null;
    name: string | null;
    avatar: string | null;
    linked_at: string;
}

/** 已連結帳號頁的一列：provider 與綁定資訊（未綁定為 null）。 */
export interface ConnectedProvider extends SocialProviderOption {
    account: LinkedSocialAccount | null;
}
