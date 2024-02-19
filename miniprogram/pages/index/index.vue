<template>
	<view class="content">
		<view class="cell-left">
			<view class="cell-text">照片上传：</view>
			<view class="cell-text" style="color: #8C8C8C;width: 180px;">(最多上传3张图片)</view>
		</view>
		<view class="cell-left">
			<view class="qtpicker">
				<!-- 选中待上传的图片 -->
				<view class="preImgs" v-for="(val,index) in preImgUrl" :key='index'>
					<image style="border-radius: 6px;" mode="" :src="val" @click="showImg(val,index)">
					</image>
					<!-- 删除某张图片 -->
					<view v-show="isShowDelImgBtn">
						<image class="cuo" mode="" src="/static/delete-icon.png" @click="delImg(index)">
						</image>
					</view>
				</view>
				
				<view v-show="isShowAddImgBtn">
					<view class="img-item upload-icon" @click="chooseImg"></view>
				</view>
			</view>
		</view>
	</view>
</template>

<script>
	import crypto from 'crypto-js';
	import { Base64 } from 'js-base64';
	export default {
		data() {
			return {
				//title: 'Hello',
				preImgUrl: [], //本地预览的图片数据
				ossImgUrl:[],//oss图片数据
				ossAccessKeyId:'',
				ossAccessKeySecret:'',
				host:'',
				securityToken:'',
				policy:'',
				isShowDelImgBtn:true,
				isShowAddImgBtn: true,
			}
		},
		onLoad() {
			
		},
		created() {
			this.getStsToken();
		},
		methods: {
			// 将方法标记为异步
			async getStsToken() {
				let that = this
				// 调用后端接口
				const result = uni.request({
					url: 'http://localhost/api/getStsToken',
					method: 'POST',
				}).then(response => {
					// response 就是 Promise 的 [[PromiseResult]] 属性对应的值
					const { data } = response;
					console.log(data);
					// 现在你可以直接访问和过滤data中的内容
					that.host = data.host;
					that.ossAccessKeyId = data.AccessKeyId;
					that.ossAccessKeySecret = data.AccessKeySecret;
					that.securityToken = data.SecurityToken;
					const policyText = {
						expiration: data.Expiration, // policy过期时间。根据自己接口返回的格式进行获取数据
						conditions: [
							// 限制上传大小。
							["content-length-range", 0, 1024 * 1024 * 1024],
						],
					};
					this.policy = Base64.encode(JSON.stringify(policyText)) // policy必须为base64的string。
				}).catch(error => {
					console.error('获取STS Token时出错:', error);
				});
			},

			computeSignature(accessKeySecret, canonicalString) {
				return crypto.enc.Base64.stringify(crypto.HmacSHA1(canonicalString, accessKeySecret));
			},

			// 选择图片
			chooseImg() {
				let that = this
				
				uni.chooseImage({
					// count:  允许上传的照片数量
					count: 3, //h5无法限制
					// sizeType:  original 原图，compressed 压缩图，默认二者都有
					sizeType: "original，compressed",
					success: function(res) { //选择成功，将图片存入本地临时路径，可删除，可查看，等待上传
						console.log(res, '选择成功')

						// 如果限制图片大小，则添加判断
						res.tempFiles.map(val => {
							// 判断本次上传限制的图片大小
							if (val.size > 10485760) {
								uni.showToast({
									icon: 'none',
									title: '上传的图片大小不超过10M'
								})
								return
							}
 
							// 判断本次最多上传多少照片
							that.imgNum++
							if(that.imgNum==3){
								that.isShowAddImgBtn=false
								uni.showToast({
									icon: 'none',
									title: '最多上传3张图片'
								})
							}
							if (that.imgNum > 3) {
								that.imgNum = 3
								uni.showToast({
									icon: 'none',
									title: '上传的图片最多不能超过3张'
								})
								return
							}
							const filePath = val.path; // 待上传文件的文件路径。
							//把临时路径添加进数组，渲染到页面
							that.preImgUrl.push(filePath) 

							// 加入时间戳-以免文件名重复
							let unixTime = String(Date.parse(new Date()) / 1000)

							//获取最后一个.的位置
							let fileIndex = filePath.lastIndexOf(".");
							//获取文件后缀
							let fileExt = filePath.substring(fileIndex + 1);
							//文件名
						    let key = 'test/'+unixTime+'.'+fileExt;
						
							const signature = that.computeSignature(that.ossAccessKeySecret, that.policy);
							console.log(signature);
							//上传图片到阿里云oss
							const host = that.host;
							const policy = that.policy;
							const ossAccessKeyId = that.ossAccessKeyId;
							const securityToken = that.securityToken; 
							
							uni.uploadFile({
								url: host,
								filePath: filePath,
								name: 'file', // 必须填file。
		
								formData: {
									key,
									policy,
									OSSAccessKeyId: ossAccessKeyId,
									signature,
									'x-oss-security-token': securityToken,
									//success_action_status: 200, // 自定义成功返回的http状态码，默认为204
								},
								success: (res) => {
									console.log(res);
									if (res.statusCode === 204) {
										//console.log('上传成功');
										// 拼接上传成功的图片URL
										const imageUrl = host + '/' + key;
										// 将上传成功后的图片URL添加到已上传图片URL数组中
										that.ossImgUrl.push(imageUrl);
									}
								},
								fail: err => {
									console.log(err);
								}
							});
						})
					}
				})
			},

			//点击小图查看大图片
			showImg(val, index) {
				console.log(val, '点击了')
				let that = this
				uni.previewImage({
					// 对选中的图片进行预览
					urls: that.preImgUrl, //图片数组  // urls:['','']  图片的地址 是数组形式
					current: index, //当前图片的下标
				})
			},

			//删除某张图片，从本地的临时路径图片中, 删除路径即可
			delImg(index) {
				this.imgNum--;
				this.preImgUrl.splice(index, 1)
				if(this.imgNum<3){
					this.isShowAddImgBtn=true;
				}
			},
		}
	}
</script>

<style lang="scss" scoped>
	.content {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
	}

	.logo {
		height: 200rpx;
		width: 200rpx;
		margin-top: 200rpx;
		margin-left: auto;
		margin-right: auto;
		margin-bottom: 50rpx;
	}

	.text-area {
		display: flex;
		justify-content: center;
	}

	.title {
		font-size: 36rpx;
		color: #8f8f94;
	}

	.upload-icon {
		box-sizing: border-box;
		border: 2rpx solid #bfbfbf;
	}

	.upload-icon:before {
		content: '';
		position: absolute;
		top: 50%;
		left: 50%;
		width: 60rpx;
		height: 6rpx;
		background-color: #bfbfbf;
		margin: -3rpx 0 0 -30rpx;
		border-radius: 5rpx;
	}

	.upload-icon::after {
		content: '';
		position: absolute;
		top: 50%;
		left: 50%;
		width: 6rpx;
		height: 60rpx;
		background-color: #bfbfbf;
		margin: -30rpx 0 0 -3rpx;
		border-radius: 5rpx;
	}

	.cell-left {
		display: flex;
		align-items: center;
		padding: 8px;
		.cell-icon {
			width: 50rpx;
			height: 50rpx;
		}

		.cell-text {
			color: #595959 ;
			font-size: 15px;
			margin-left: 20rpx;
			//width: 180rpx;
		}
	}

	.img-item {
		width: 150rpx;
		height: 150rpx;
		position: relative;
		box-sizing: border-box;
		margin: 15rpx;

		.img {
			width: 100%;
			height: 100%;
		}

		.img-delete-box {
			width: 40rpx;
			height: 40rpx;
			position: absolute;
			right: 0;
			top: 0;

			.img-delete-icon {
				width: 100%;
				height: 100%;
			}
		}

	}

	.qtpicker {
		width: 100%;
		display: flex;
		flex-direction: row;
		flex-wrap: wrap;
		margin: 0 auto;
		padding: 10rpx 0;
 
		.preImgs {
			margin: 13rpx;
			position: relative;
 
			image {
				width: 200rpx;
				height: 200rpx;
			}
 
			.cuo {
				width: 17pt;
				height: 17pt;
				//line-height: 12px;
				//text-align: center;
				///* font-size: 16px; */
				//border-radius: 50%;
				//background-color: #223E4B;
				//color: #FFFFFF;
				position: absolute;
				right: 0px;
				top: 0px;
			}
		}
	}
</style>
