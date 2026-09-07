"use client";

import { FormEvent, useEffect, useState } from "react";
import styles from "../../page.module.css";

const API=process.env.NEXT_PUBLIC_API_URL??"http://127.0.0.1:8888/api";

export default function PasswordSetupPage(){
  const [params,setParams]=useState({email:"",token:"",ready:false});
  useEffect(()=>{const p=new URLSearchParams(window.location.search);setParams({email:p.get("email")||"",token:p.get("token")||"",ready:true})},[]);
  const [password,setPassword]=useState("");const [confirmation,setConfirmation]=useState("");const [message,setMessage]=useState("");const [done,setDone]=useState(false);const [loading,setLoading]=useState(false);
  async function submit(e:FormEvent){e.preventDefault();setLoading(true);setMessage("");try{const r=await fetch(`${API}/password/setup`,{method:"POST",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify({email:params.email,token:params.token,password,password_confirmation:confirmation})});const data=await r.json();if(!r.ok)throw new Error(data.message||"パスワードを設定できませんでした。");setDone(true);setMessage(data.message)}catch(e){setMessage((e as Error).message)}finally{setLoading(false)}}
  if(!params.ready)return <div className={styles.loginPage}/>;
  if(!params.email||!params.token)return <div className={styles.loginPage}><section className={styles.loginCard}><h2>リンクが不正です</h2><p>メールのリンクをもう一度確認してください。</p></section></div>;
  return <div className={styles.loginPage}><section className={styles.loginCard}><h2>パスワードを設定</h2><p>{params.email}のログインパスワードを設定します。</p>{done?<><p className={styles.setupSuccess}>{message}</p><a className={styles.primaryLink} href="/">ログイン画面へ</a></>:<form onSubmit={submit}><label>新しいパスワード<input type="password" minLength={8} value={password} onChange={e=>setPassword(e.target.value)} required/></label><label>パスワード（確認）<input type="password" minLength={8} value={confirmation} onChange={e=>setConfirmation(e.target.value)} required/></label>{message&&<p className={styles.setupError}>{message}</p>}<button disabled={loading}>{loading?"設定中…":"パスワードを設定する"}</button></form>}</section></div>
}
